<?php

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use App\Models\Post;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationFuzzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.location_fuzz.min_m' => 120, 'dawat.location_fuzz.max_m' => 250]);
    }

    public function test_public_location_is_120_to_250_m_from_home(): void
    {
        foreach (range(1, 20) as $i) {
            $kitchen = Kitchen::factory()->at(23.8069, 90.3687)->create()->fresh();
            $offset = $kitchen->location->distanceTo($kitchen->public_location);

            $this->assertGreaterThanOrEqual(119, $offset);
            $this->assertLessThanOrEqual(251, $offset);
        }
    }

    public function test_the_offset_stays_fixed_until_the_cook_moves(): void
    {
        $kitchen = Kitchen::factory()->at(23.8069, 90.3687)->create();
        $first = $kitchen->fresh()->public_location;

        // Other edits, through the API and directly, must not re-roll the point.
        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['display_name' => 'নতুন নাম', 'allow_call' => true])
            ->assertOk();
        $kitchen->fresh()->update(['allow_comment' => false]);
        $kitchen->fresh()->save();

        $this->assertEquals($first->toArray(), $kitchen->fresh()->public_location->toArray());

        // Moving house gives a new point.
        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['lat' => 23.7461, 'lng' => 90.3742])
            ->assertOk();
        $moved = $kitchen->fresh();
        $this->assertNotEquals($first->toArray(), $moved->public_location->toArray());
        $this->assertLessThanOrEqual(251, $moved->location->distanceTo($moved->public_location));
    }

    public function test_posts_and_the_feed_use_the_public_point_never_the_home(): void
    {
        $kitchen = Kitchen::factory()->approved()->at(23.8069, 90.3687)->create();
        $this->actingAs($kitchen->user, 'sanctum');
        $post = Post::factory()->create(['kitchen_id' => $kitchen->id])->fresh();
        $kitchen->refresh();

        $this->assertEquals($kitchen->public_location->toArray(), $post->location->toArray());
        $this->assertGreaterThan(100, $post->location->distanceTo($kitchen->location));

        // Standing exactly at the real home, the buyer is told it is 100+ m away.
        $this->app['auth']->forgetGuards();
        $shown = $this->getJson('/api/v1/feed?lat=23.8069&lng=90.3687')->json('data.0.distance_m');
        $this->assertGreaterThanOrEqual(150, $shown);
    }

    public function test_public_location_is_hidden_from_serialisation(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->assertArrayNotHasKey('public_location', $kitchen->toArray());
    }

    public function test_existing_data_without_fuzz_setting_falls_back_to_exact(): void
    {
        config(['dawat.location_fuzz.min_m' => 0, 'dawat.location_fuzz.max_m' => 0]);
        $kitchen = Kitchen::factory()->at(23.8069, 90.3687)->create()->fresh();

        $this->assertEqualsWithDelta(0, $kitchen->location->distanceTo($kitchen->public_location), 0.01);
        $this->assertInstanceOf(GeoPoint::class, $kitchen->public_location);
    }
}
