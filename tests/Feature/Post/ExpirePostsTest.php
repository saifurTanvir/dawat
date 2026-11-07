<?php

namespace Tests\Feature\Post;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_only_posts_whose_time_is_up(): void
    {
        $due = Post::factory()->create(['available_until' => now()->subMinute()]);
        $dueSoldOut = Post::factory()->create(['available_until' => now()->subMinute(), 'status' => PostStatus::SoldOut]);
        $running = Post::factory()->create(['available_until' => now()->addHour()]);
        $hidden = Post::factory()->create(['available_until' => now()->subMinute(), 'status' => PostStatus::Hidden]);

        $this->artisan('posts:expire')->expectsOutput('Expired 2 post(s).')->assertSuccessful();

        $this->assertSame(PostStatus::Expired, $due->fresh()->status);
        $this->assertSame(PostStatus::Expired, $dueSoldOut->fresh()->status);
        $this->assertSame(PostStatus::Live, $running->fresh()->status);
        // An admin's decision is never overwritten.
        $this->assertSame(PostStatus::Hidden, $hidden->fresh()->status);
    }

    public function test_it_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'posts:expire'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }

    public function test_cook_list_shows_expired_after_the_run(): void
    {
        $post = Post::factory()->create(['available_until' => now()->addMinutes(30)]);

        $this->travel(31)->minutes();
        $this->artisan('posts:expire');

        $this->actingAs($post->kitchen->user, 'sanctum')
            ->getJson('/api/v1/my/posts')
            ->assertJsonPath('data.0.status', 'expired');
    }
}
