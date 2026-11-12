<?php

namespace Tests\Feature\Home;

use App\Enums\PostStatus;
use App\Models\Kitchen;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function withPhoto(Post $post): Post
    {
        $post->images()->create(['path' => "posts/{$post->id}.jpg", 'sort_order' => 0, 'width' => 800, 'height' => 600]);

        return $post;
    }

    public function test_counts_todays_food_cooks_and_stars(): void
    {
        $a = Kitchen::factory()->approved()->create(['rating_avg' => 5, 'rating_count' => 2]);
        $b = Kitchen::factory()->approved()->create(['rating_avg' => 4, 'rating_count' => 2]);
        Kitchen::factory()->create(); // pending: not counted

        $this->withPhoto(Post::factory()->create(['kitchen_id' => $a->id, 'price_paisa' => 15000]));
        Post::factory()->create(['kitchen_id' => $b->id, 'price_paisa' => 12000]);
        Post::factory()->create(['kitchen_id' => $b->id, 'price_paisa' => 9000, 'available_until' => now()->subMinute()]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.today.count', 2)
            ->assertJsonPath('data.today.min_price_paisa', 12000)
            ->assertJsonPath('data.kitchens', 2)
            ->assertJsonPath('data.rating.avg', 4.5)
            ->assertJsonPath('data.rating.count', 4)
            ->assertJsonCount(1, 'data.samples')
            ->assertJsonPath('data.samples.0.today', true);
    }

    public function test_falls_back_to_earlier_photos_without_linking_them_and_never_shows_location(): void
    {
        $old = $this->withPhoto(Post::factory()->create([
            'status' => PostStatus::Expired, 'available_until' => now()->subDay(), 'published_at' => now()->subDay(),
        ]));

        $response = $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.today.count', 0)
            ->assertJsonPath('data.samples.0.title', $old->title)
            ->assertJsonPath('data.samples.0.id', null)
            ->assertJsonPath('data.samples.0.today', false);

        $this->assertArrayNotHasKey('location', $response->json('data.samples.0'));
        $this->assertStringNotContainsString('"lat"', $response->getContent());
        $this->assertStringNotContainsString('"lng"', $response->getContent());
    }
}
