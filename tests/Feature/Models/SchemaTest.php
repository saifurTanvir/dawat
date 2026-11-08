<?php

namespace Tests\Feature\Models;

use App\Enums\PostStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Geo\GeoPoint;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_geo_point_round_trips_through_mysql(): void
    {
        $kitchen = Kitchen::factory()->at(23.8069, 90.3654)->create();

        $fresh = Kitchen::find($kitchen->id);

        $this->assertInstanceOf(GeoPoint::class, $fresh->location);
        $this->assertEqualsWithDelta(23.8069, $fresh->location->lat, 1e-6);
        $this->assertEqualsWithDelta(90.3654, $fresh->location->lng, 1e-6);
    }

    public function test_post_copies_kitchen_location_and_area(): void
    {
        $post = Post::factory()->create();
        $post->refresh();

        $this->assertEquals($post->kitchen->area_id, $post->area_id);
        $this->assertEqualsWithDelta($post->kitchen->location->lat, $post->location->lat, 1e-6);
    }

    public function test_visible_now_hides_expired_sold_out_and_hidden_posts(): void
    {
        $live = Post::factory()->create();
        Post::factory()->create(['available_until' => now()->subMinute()]);
        Post::factory()->create(['plates_left' => 0]);
        Post::factory()->create(['status' => PostStatus::Hidden]);

        $this->assertEquals([$live->id], Post::visibleNow()->pluck('id')->all());
    }

    public function test_location_is_never_serialised(): void
    {
        $post = Post::factory()->create();

        $this->assertArrayNotHasKey('location', $post->toArray());
        $this->assertArrayNotHasKey('location', $post->kitchen->toArray());
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(13, Tag::count());
        $this->assertSame(12, Area::count());
        $this->assertNotNull(Area::where('slug', 'uttar-badda')->first()->center);
    }
}
