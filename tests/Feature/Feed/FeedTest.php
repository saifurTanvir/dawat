<?php

namespace Tests\Feature\Feed;

use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Post;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\PublicDistance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    /** Buyer standing at Mirpur 10 roundabout. */
    private const HERE = ['lat' => 23.8069, 'lng' => 90.3687];

    /** A post whose kitchen is $metres east of HERE. */
    private function postEast(int $metres, array $attributes = []): Post
    {
        $dLng = $metres / (111_320 * cos(deg2rad(self::HERE['lat'])));
        $kitchen = Kitchen::factory()->approved()->at(self::HERE['lat'], self::HERE['lng'] + $dLng)->create();

        return Post::factory()->create(['kitchen_id' => $kitchen->id, ...$attributes]);
    }

    public function test_feed_is_public_and_sorted_nearest_first(): void
    {
        $far = $this->postEast(2500);
        $near = $this->postEast(250);
        $mid = $this->postEast(900);

        $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$near->id, $mid->id, $far->id])
            ->assertJsonPath('meta.origin', 'location')
            ->assertJsonPath('meta.radius', '3km');
    }

    public function test_radius_filter(): void
    {
        $near = $this->postEast(250);
        $this->postEast(900);
        $this->postEast(2500);
        $this->postEast(5000);

        $this->getJson('/api/v1/feed?'.http_build_query([...self::HERE, 'radius' => 'walk']))
            ->assertJsonPath('data.*.id', [$near->id]);
        $this->getJson('/api/v1/feed?'.http_build_query([...self::HERE, 'radius' => '1km']))
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/feed?'.http_build_query([...self::HERE, 'radius' => 'all']))
            ->assertJsonCount(4, 'data');
    }

    public function test_meal_filter(): void
    {
        $lunch = $this->postEast(300, ['meal_slot' => MealSlot::Lunch]);
        $this->postEast(300, ['meal_slot' => MealSlot::Snack]);

        $this->getJson('/api/v1/feed?'.http_build_query([...self::HERE, 'meal' => 'lunch']))
            ->assertJsonPath('data.*.id', [$lunch->id]);
    }

    public function test_only_sellable_posts_from_approved_kitchens_show(): void
    {
        $ok = $this->postEast(300);
        $this->postEast(300, ['status' => PostStatus::SoldOut, 'plates_left' => 0]);
        $this->postEast(300, ['status' => PostStatus::Hidden]);
        $this->postEast(300, ['available_until' => now()->subMinute()]);
        $suspended = $this->postEast(300);
        $suspended->kitchen->update(['status' => KitchenStatus::Suspended]);
        $blocked = $this->postEast(300);
        $blocked->kitchen->user->update(['status' => 'blocked']);

        $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))
            ->assertJsonPath('data.*.id', [$ok->id]);
    }

    public function test_area_is_used_when_no_location_is_shared(): void
    {
        $area = Area::factory()->create(['slug' => 'mirpur-10', 'center' => GeoPoint::make(...array_values(self::HERE))]);
        $post = $this->postEast(400);

        $this->getJson('/api/v1/feed?area=mirpur-10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('meta.origin', 'area')
            ->assertJsonPath('meta.area.slug', 'mirpur-10');
    }

    public function test_without_location_or_area_the_buyer_is_asked(): void
    {
        $this->getJson('/api/v1/feed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['origin']);

        $this->getJson('/api/v1/feed?area=nowhere')->assertJsonValidationErrors(['area']);
        $this->getJson('/api/v1/feed?lat=51.5&lng=-0.1')->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_distance_is_rounded_and_has_walking_time(): void
    {
        $this->postEast(260);

        $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))
            ->assertJsonPath('data.0.distance_m', 300)
            ->assertJsonPath('data.0.walk_minutes', 5);
    }

    public function test_very_close_posts_never_show_less_than_100_m(): void
    {
        $this->postEast(10);

        $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))
            ->assertJsonPath('data.0.distance_m', 100);
    }

    public function test_feed_never_leaks_coordinates_or_phone_numbers(): void
    {
        $post = $this->postEast(400);
        $post->kitchen->update(['contact_phone' => '01812345678', 'contact_name' => 'করিম', 'allow_call' => true]);
        $loc = $post->kitchen->fresh()->location;

        $response = $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))->assertOk();
        // Only the posts; links/meta echo the buyer's own position, which is theirs to know.
        $json = json_encode($response->json('data'));

        $keys = [];
        $collect = function (array $node) use (&$collect, &$keys) {
            foreach ($node as $key => $value) {
                $keys[] = $key;
                if (is_array($value)) {
                    $collect($value);
                }
            }
        };
        $collect($response->json('data'));
        foreach (['location', 'lat', 'lng', 'phone', 'contact_phone', 'user_id', 'kitchen_id', 'area_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "feed leaked {$forbidden}");
        }
        $this->assertStringNotContainsString(substr((string) $loc->lng, 0, 7), $json);
        $this->assertStringNotContainsString('01812345678', $json);
        $this->assertStringNotContainsString($post->kitchen->user->phone, $json);

        $response->assertJsonPath('data.0.contact', ['call' => true, 'message' => true, 'comment' => true])
            ->assertJsonPath('data.0.kitchen.name', $post->kitchen->display_name);
    }

    public function test_public_distance_rules(): void
    {
        $this->assertSame(100, PublicDistance::round(0));
        $this->assertSame(100, PublicDistance::round(99));
        $this->assertSame(150, PublicDistance::round(101));
        $this->assertSame(1000, PublicDistance::round(1000));
        $this->assertSame(2, PublicDistance::walkMinutes(10));
        $this->assertSame(17, PublicDistance::walkMinutes(1000));
    }

    public function test_feed_is_rate_limited(): void
    {
        foreach (range(1, 60) as $i) {
            $this->getJson('/api/v1/feed?'.http_build_query(self::HERE));
        }

        $this->getJson('/api/v1/feed?'.http_build_query(self::HERE))->assertTooManyRequests();
    }
}
