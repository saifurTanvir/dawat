<?php

namespace Tests\Feature\Post;

use App\Enums\PostStatus;
use App\Models\Kitchen;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPostsTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kitchen = Kitchen::factory()->approved()->create();
    }

    public function test_cook_sees_only_their_recent_posts(): void
    {
        $mine = Post::factory()->create(['kitchen_id' => $this->kitchen->id]);
        $old = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'created_at' => now()->subDays(10)]);
        Post::factory()->create();

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->getJson('/api/v1/my/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.contact_counts', 0)
            ->assertJsonMissingPath('data.0.location');
    }

    public function test_updating_plates_left_and_auto_sold_out(): void
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'plates_total' => 3, 'plates_left' => 3]);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 1])
            ->assertOk()
            ->assertJsonPath('data.plates_left', 1)
            ->assertJsonPath('data.status', 'live');

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 0])
            ->assertJsonPath('data.status', 'sold_out');

        // Found one more plate: back on sale.
        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 1])
            ->assertJsonPath('data.status', 'live');
    }

    public function test_plates_left_cannot_exceed_total(): void
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'plates_total' => 2, 'plates_left' => 2]);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 5])
            ->assertJsonValidationErrors(['plates_left']);
    }

    public function test_mark_sold_out(): void
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id]);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->postJson("/api/v1/my/posts/{$post->id}/sold-out")
            ->assertOk()
            ->assertJsonPath('data.status', 'sold_out')
            ->assertJsonPath('data.plates_left', 0);
    }

    public function test_another_cooks_post_is_not_found(): void
    {
        $other = Post::factory()->create();

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->postJson("/api/v1/my/posts/{$other->id}/sold-out")
            ->assertNotFound();

        $this->assertSame(PostStatus::Live, $other->fresh()->status);
    }

    public function test_hidden_or_expired_posts_cannot_be_reopened(): void
    {
        $hidden = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'status' => PostStatus::Hidden]);
        $past = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'available_until' => now()->subMinute()]);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$hidden->id}", ['plates_left' => 1])
            ->assertForbidden();

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->patchJson("/api/v1/my/posts/{$past->id}", ['plates_left' => 1])
            ->assertForbidden();
    }
}
