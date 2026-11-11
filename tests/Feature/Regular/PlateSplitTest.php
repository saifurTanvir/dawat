<?php

namespace Tests\Feature\Regular;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Widgets\TodayPlates;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Services\Posts\PlateSplit;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F44: everyone can see where a post's plates went; the cook never sees who. */
class PlateSplitTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $nasir;

    private Customer $rahim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        foreach (['nasir' => ['নাসির উদ্দিন', 2, null], 'rahim' => ['রহিম মিয়া', 1, 10000]] as $key => [$name, $plates, $limit]) {
            $c = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Active, 'meal_slots' => ['lunch'], 'plates' => $plates, 'max_price_paisa' => $limit]);
            $c->user->update(['name' => $name]);
            Pairing::create(['customer_id' => $c->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
            $this->{$key} = $c;
        }
    }

    private function cookPosts(int $plates = 6, int $price = 120)
    {
        return $this->actingAs($this->kitchen->user, 'sanctum')->post('/api/v1/posts', [
            'images' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
            'title' => 'ভাত, মাছ', 'price_taka' => $price, 'plates_total' => $plates, 'meal_slot' => 'lunch', 'available_hours' => 3,
        ], ['Accept' => 'application/json']);
    }

    public function test_preview_before_posting_shows_numbers_only(): void
    {
        $line = $this->actingAs($this->kitchen->user, 'sanctum')
            ->getJson('/api/v1/my/posts/preview?meal_slot=lunch&plates=6&price_taka=120')
            ->assertOk()->assertJsonPath('data.needed', 3)->assertJsonPath('data.to_feed', 3)->assertJsonPath('data.asking_people', 1)
            ->json('data.line');

        $this->assertSame('নিয়মিত গ্রাহক ২ জন — ৩ প্লেট আগে যাবে। ফিডে যাবে ৩ প্লেট। ১ জনের দামের সীমা কম, তাঁদের আগে জিজ্ঞেস করা হবে।', $line);
        $this->assertStringNotContainsString('নাসির', $line);
    }

    public function test_after_posting_the_cook_sees_the_split_without_names_and_shahidar_is_told(): void
    {
        Queue::fake();
        $this->cookPosts()->assertCreated()
            ->assertJsonPath('data.split.regular', 2)
            ->assertJsonPath('data.split.asking', 1)
            ->assertJsonPath('data.split.left', 3)
            ->assertJsonPath('message', 'পোস্ট হয়ে গেছে। ২ প্লেট নিয়মিত গ্রাহকদের বুক হয়েছে · ১ জনকে দাম জিজ্ঞেস করা হচ্ছে · ৩ প্লেট ফিডে গেছে।');

        $bookings = $this->getJson('/api/v1/my/bookings')->assertOk()->json();
        $this->assertStringNotContainsString('নাসির', json_encode($bookings, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('রহিম', json_encode($bookings, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('নাসির', json_encode($this->getJson('/api/v1/my/posts')->json(), JSON_UNESCAPED_UNICODE));

        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $this->shahidar->id
            && str_contains($job->body, 'আপনার ১টা বক্স বুক হয়েছে') && str_contains($job->body, '১ জনকে দাম জিজ্ঞেস করুন'));
    }

    public function test_feed_orders_and_cancellations_move_the_split(): void
    {
        $this->cookPosts()->assertCreated();
        $post = Post::sole();
        $buyer = User::factory()->create();
        Order::create([
            'service_date' => '2026-11-11', 'seq' => 50, 'meal_slot' => 'lunch', 'kitchen_id' => $this->kitchen->id, 'post_id' => $post->id,
            'buyer_id' => $buyer->id, 'kind' => 'oneoff', 'plates' => 1, 'food_paisa' => 12000, 'delivery_fee_paisa' => 0, 'status' => OrderStatus::Booked,
        ]);
        $post->decrement('plates_left');

        $s = app(PlateSplit::class)->forPost($post->fresh());
        $this->assertSame(['total' => 6, 'regular' => 2, 'regular_people' => 1, 'asking' => 1, 'asking_people' => 1, 'feed_orders' => 1, 'left' => 2], $s);
        $this->assertSame('নিয়মিত ২ · দাম জিজ্ঞেস ১ · ফিডে অর্ডার ১ · ফিডে বাকি ২', PlateSplit::line($s));
    }

    public function test_shahidar_sees_todays_posts_with_names(): void
    {
        $this->cookPosts()->assertCreated();

        $post = $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')->assertOk()->json('data.posts.0');
        $this->assertSame('গৃহিণী নিজে', $post['source_label']);
        $this->assertSame(2, $post['mine']);
        $this->assertContains('নাসির উদ্দিন', array_column($post['people'], 'name'));

        $this->getJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/preview?meal_slot=dinner&plates=4")
            ->assertOk()->assertJsonPath('data.needed', 0);
    }

    public function test_daily_menu_shows_a_preview_for_today(): void
    {
        KitchenMenu::create([
            'kitchen_id' => $this->kitchen->id, 'meal_slot' => 'lunch', 'title' => 'খিচুড়ি', 'price_paisa' => 9000,
            'plates_total' => 5, 'available_hours' => 3, 'post_at' => '11:00', 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ]);
        $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/menus')
            ->assertJsonPath('data.0.preview', 'নিয়মিত গ্রাহক ২ জন — ৩ প্লেট আগে যাবে। ফিডে যাবে ২ প্লেট।');
    }

    public function test_admin_sees_who_got_boxes_and_todays_plates(): void
    {
        $this->cookPosts()->assertCreated();
        $other = Kitchen::factory()->approved()->create(['display_name' => 'সালমার রান্নাঘর']);
        $c = Customer::factory()->create(['area_id' => $other->area_id, 'status' => CustomerStatus::Active, 'meal_slots' => ['lunch']]);
        Pairing::create(['customer_id' => $c->id, 'kitchen_id' => $other->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewPost::class, ['record' => Post::sole()->id])
            ->assertSee('কারা পেয়েছে')->assertSee('নাসির উদ্দিন')->assertSee('নিয়মিত ২ · দাম জিজ্ঞেস ১ · ফিডে বাকি ৩');

        Livewire::test(TodayPlates::class)
            ->assertSee('আজকের প্লেট')
            ->assertSee('পোস্ট হয়েছে')
            ->assertSee('সালমার রান্নাঘর')
            ->assertSee('এখনো পোস্ট আসেনি');
    }
}
