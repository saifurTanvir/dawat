<?php

namespace Tests\Feature\Feed;

use App\Actions\PublishPost;
use App\Filament\Resources\Kitchens\Pages\EditKitchen;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F35: where a post shows, who saw it, and widening its reach. */
class ReachTest extends TestCase
{
    use RefreshDatabase;

    /** Uttar Badda centre (an F31 service area). */
    private const BADDA = ['lat' => 23.7867, 'lng' => 90.4262];

    /** Bashundhara centre, about 3.7 km north-east of Uttar Badda. */
    private const BASHUNDHARA = ['lat' => 23.8190, 'lng' => 90.4355];

    private function baddaPost(array $attrs = []): Post
    {
        $kitchen = Kitchen::factory()->approved()->at(self::BADDA['lat'], self::BADDA['lng'])
            ->create(['area_id' => Area::where('slug', 'uttar-badda')->value('id')]);

        return Post::factory()->create(['kitchen_id' => $kitchen->id, 'title' => 'বাড্ডার খিচুড়ি', ...$attrs]);
    }

    private function feedFrom(array $point, string $radius = '1km')
    {
        return $this->getJson('/api/v1/feed?'.http_build_query([...$point, 'radius' => $radius]));
    }

    public function test_a_normal_post_shows_only_within_the_distance_the_buyer_picked(): void
    {
        $post = $this->baddaPost();

        $this->feedFrom(self::BASHUNDHARA, '1km')->assertOk()->assertJsonCount(0, 'data');
        $this->feedFrom(self::BASHUNDHARA, '3km')->assertJsonCount(0, 'data');
        $this->feedFrom(self::BASHUNDHARA, 'all')->assertJsonPath('data.0.id', $post->id);
    }

    public function test_a_wider_radius_shows_the_post_whatever_distance_was_picked(): void
    {
        $post = $this->baddaPost();
        $post->setReach(5000, []);

        $this->feedFrom(self::BASHUNDHARA, '1km')->assertJsonPath('data.0.id', $post->id);

        $post->setReach(2000, []);
        $this->feedFrom(self::BASHUNDHARA, '1km')->assertJsonCount(0, 'data');
    }

    public function test_an_added_area_shows_the_post_to_everyone_standing_in_it(): void
    {
        $post = $this->baddaPost();
        $post->setReach(null, [Area::where('slug', 'bashundhara')->value('id')]);

        $this->feedFrom(self::BASHUNDHARA, 'walk')->assertJsonPath('data.0.id', $post->id);
        // Gulshan 2 was not added.
        $this->feedFrom(['lat' => 23.7946, 'lng' => 90.4143], '1km')->assertJsonCount(0, 'data');

        $post->setReach(null, []);
        $this->assertFalse($post->fresh()->boosted);
        $this->feedFrom(self::BASHUNDHARA, 'walk')->assertJsonCount(0, 'data');
    }

    public function test_the_feed_tells_the_buyer_which_area_they_are_near_without_coordinates(): void
    {
        $this->feedFrom(['lat' => 23.7870, 'lng' => 90.4265], '3km')
            ->assertJsonPath('meta.near_area.name', 'উত্তর বাড্ডা')
            ->assertJsonPath('meta.radius_m', 3000)
            ->assertJsonMissingPath('meta.near_area.center');
    }

    public function test_views_are_counted_once_a_day_per_viewer_and_keep_no_coordinates(): void
    {
        $post = $this->baddaPost();
        $here = ['lat' => 23.7870, 'lng' => 90.4300];

        $this->feedFrom($here, '1km')->assertJsonPath('data.0.id', $post->id);
        $this->feedFrom($here, '1km');
        $this->assertSame(1, PostView::count());

        $view = PostView::first();
        $this->assertNull($view->user_id);
        $this->assertSame('feed', $view->source);
        $this->assertSame(Area::where('slug', 'uttar-badda')->value('id'), $view->area_id);
        $this->assertSame(0, $view->distance_m % 100);

        $buyer = User::factory()->create();
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/posts/'.$post->id)->assertOk();
        $this->assertSame(2, PostView::count());
        $this->assertSame('detail', PostView::where('user_id', $buyer->id)->value('source'));

        // The cook looking at her own post is not counted.
        $this->actingAs($post->kitchen->user, 'sanctum')->getJson('/api/v1/posts/'.$post->id);
        $this->assertSame(2, PostView::count());

        $this->travel(1)->day();
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/posts/'.$post->id);
        $this->assertSame(3, PostView::count());
    }

    public function test_a_widened_kitchen_passes_its_reach_to_new_posts(): void
    {
        $kitchen = Kitchen::factory()->approved()->at(self::BADDA['lat'], self::BADDA['lng'])->create(['reach_m' => 5000]);
        $kitchen->reachAreas()->sync([Area::where('slug', 'banani')->value('id')]);

        $post = app(PublishPost::class)->handle($kitchen, [
            'title' => 'ডাল-ভাত', 'price_taka' => 120, 'plates_total' => 3, 'meal_slot' => 'lunch', 'available_hours' => 3,
        ], []);

        $this->assertSame(5000, $post->fresh()->reach_m);
        $this->assertTrue($post->fresh()->boosted);
        $this->assertSame(['banani'], $post->reachAreas()->pluck('slug')->all());
    }

    public function test_admin_sees_where_a_post_shows_and_who_saw_it_and_can_widen_it(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $post = $this->baddaPost();
        $buyer = User::factory()->create(['name' => 'রাশেদ']);
        PostView::create([
            'post_id' => $post->id, 'user_id' => $buyer->id, 'viewer_key' => sha1('x'),
            'area_id' => Area::where('slug', 'uttar-badda')->value('id'), 'distance_m' => 300, 'source' => 'feed', 'viewed_on' => today(),
        ]);

        $this->get("/admin/posts/{$post->id}")
            ->assertOk()
            ->assertSee('কোথায় দেখাচ্ছে')
            ->assertSee('data-reach-map', false)
            ->assertSee('বসুন্ধরা আবাসিক এলাকা')
            ->assertSee('শুধু "পুরো এলাকা" বাছলে দেখবে')
            ->assertSee('কারা দেখেছে')
            ->assertSee('রাশেদ');

        $bashundhara = Area::where('slug', 'bashundhara')->value('id');
        Livewire::test(ViewPost::class, ['record' => $post->id])
            ->callAction('reach', ['reach_m' => 5000, 'reach_area_ids' => [$bashundhara]])
            ->assertHasNoActionErrors();

        $post->refresh();
        $this->assertSame(5000, $post->reach_m);
        $this->assertTrue($post->boosted);
        $this->assertSame([$bashundhara], $post->reachAreas()->pluck('areas.id')->all());

        Livewire::test(ListPosts::class)->assertCanSeeTableRecords([$post])->assertSee('৫ কিমি + ১টা এলাকা');
    }

    public function test_changing_a_kitchens_reach_applies_to_its_live_posts(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $post = $this->baddaPost();
        $old = Post::factory()->create(['kitchen_id' => $post->kitchen_id, 'available_until' => now()->subHour()]);
        $gulshan = Area::where('slug', 'gulshan-2')->value('id');

        Livewire::test(EditKitchen::class, ['record' => $post->kitchen_id])
            ->fillForm(['reach_m' => 3000, 'reachAreas' => [$gulshan]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3000, $post->kitchen->fresh()->reach_m);
        $this->assertSame(3000, $post->fresh()->reach_m);
        $this->assertSame([$gulshan], $post->reachAreas()->pluck('areas.id')->all());
        $this->assertNull($old->fresh()->reach_m);
    }
}
