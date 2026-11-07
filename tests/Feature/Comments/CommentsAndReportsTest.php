<?php

namespace Tests\Feature\Comments;

use App\Enums\CommentStatus;
use App\Enums\ContactType;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\ContactEvent;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = Post::factory()->create();
        $this->buyer = User::factory()->create(['name' => 'তানভীর আহমেদ']);
    }

    public function test_post_page_is_public_and_marks_availability(): void
    {
        $this->getJson("/api/v1/posts/{$this->post->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->post->id)
            ->assertJsonPath('meta.is_available', true)
            ->assertJsonMissingPath('data.distance_m');

        $this->post->update(['status' => PostStatus::SoldOut, 'plates_left' => 0]);
        $this->getJson("/api/v1/posts/{$this->post->id}")->assertJsonPath('meta.is_available', false);

        $this->post->update(['status' => PostStatus::Hidden]);
        $this->getJson("/api/v1/posts/{$this->post->id}")->assertNotFound();
    }

    public function test_is_mine_tells_the_cook_without_exposing_ids(): void
    {
        $this->getJson("/api/v1/posts/{$this->post->id}")->assertJsonPath('data.is_mine', false);

        $this->actingAs($this->post->kitchen->user, 'sanctum')
            ->getJson("/api/v1/posts/{$this->post->id}")
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonMissingPath('data.kitchen.user_id');
    }

    public function test_post_page_gives_rounded_distance_when_asked(): void
    {
        $loc = $this->post->kitchen->location;

        $this->getJson("/api/v1/posts/{$this->post->id}?lat={$loc->lat}&lng={$loc->lng}")
            ->assertJsonPath('data.distance_m', 100);
    }

    public function test_buyer_comments_and_it_is_logged(): void
    {
        $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => '  আরেক প্লেট হবে?  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'আরেক প্লেট হবে?')
            ->assertJsonPath('data.author.name', 'তানভীর')
            ->assertJsonPath('data.author.is_cook', false)
            ->assertJsonMissingPath('data.author.phone');

        $this->assertSame(ContactType::Comment, ContactEvent::sole()->type);
    }

    public function test_guests_read_but_cannot_write(): void
    {
        Comment::create(['post_id' => $this->post->id, 'user_id' => $this->buyer->id, 'body' => 'দারুণ']);

        $this->getJson("/api/v1/posts/{$this->post->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.can_delete', false);

        $this->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'hello'])->assertUnauthorized();
    }

    public function test_cook_replies_under_kitchen_name_even_after_sold_out(): void
    {
        $this->post->update(['status' => PostStatus::SoldOut, 'plates_left' => 0]);

        $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'আছে?'])
            ->assertStatus(410);

        $this->actingAs($this->post->kitchen->user, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'আজ শেষ, কাল আবার রান্না করব'])
            ->assertCreated()
            ->assertJsonPath('data.author.is_cook', true)
            ->assertJsonPath('data.author.name', $this->post->kitchen->display_name);

        $this->assertSame(0, ContactEvent::count());
    }

    public function test_comments_off_means_no_comments(): void
    {
        $this->post->kitchen->update(['allow_comment' => false]);

        $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'hello'])
            ->assertForbidden();
    }

    public function test_author_deletes_and_cook_hides(): void
    {
        $mine = Comment::create(['post_id' => $this->post->id, 'user_id' => $this->buyer->id, 'body' => 'ভুল লিখেছি']);
        $rude = Comment::create(['post_id' => $this->post->id, 'user_id' => User::factory()->create()->id, 'body' => 'বাজে কথা']);

        $this->actingAs($this->buyer, 'sanctum')->deleteJson("/api/v1/comments/{$mine->id}")->assertNoContent();
        $this->assertModelMissing($mine);

        $this->actingAs($this->buyer, 'sanctum')->deleteJson("/api/v1/comments/{$rude->id}")->assertForbidden();

        $this->actingAs($this->post->kitchen->user, 'sanctum')->deleteJson("/api/v1/comments/{$rude->id}")->assertNoContent();
        $this->assertSame(CommentStatus::Hidden, $rude->fresh()->status);
        $this->getJson("/api/v1/posts/{$this->post->id}/comments")->assertJsonCount(0, 'data');
    }

    public function test_report_once_per_person_and_not_your_own(): void
    {
        $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'unsafe_food'])
            ->assertCreated();
        $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'spam'])
            ->assertOk();
        $this->assertSame(1, Report::count());

        $this->actingAs($this->post->kitchen->user, 'sanctum')
            ->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'spam'])
            ->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'spam'])->assertUnauthorized();
    }

    public function test_three_different_reporters_hide_a_post_until_reviewed(): void
    {
        $this->buyer->forceFill(['created_at' => now()->subWeek()])->save();
        foreach (User::factory()->count(2)->create(['created_at' => now()->subWeek()]) as $user) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'wrong_info']);
        }
        $this->assertSame(PostStatus::Live, $this->post->fresh()->status);

        $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'wrong_info']);
        $this->assertSame(PostStatus::Hidden, $this->post->fresh()->status);
    }

    public function test_reported_comment_is_hidden_after_three_reports(): void
    {
        $comment = Comment::create(['post_id' => $this->post->id, 'user_id' => $this->buyer->id, 'body' => 'বিজ্ঞাপন']);

        foreach (User::factory()->count(3)->create(['created_at' => now()->subWeek()]) as $user) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'spam'])->assertCreated();
        }

        $this->assertSame(CommentStatus::Hidden, $comment->fresh()->status);
    }

    public function test_brand_new_accounts_cannot_hide_a_post(): void
    {
        foreach (User::factory()->count(5)->create() as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/reports', ['type' => 'post', 'id' => $this->post->id, 'reason' => 'spam'])
                ->assertCreated();
        }

        // Still reported for the admin to review, but not hidden.
        $this->assertSame(5, Report::count());
        $this->assertSame(PostStatus::Live, $this->post->fresh()->status);
    }

    public function test_comments_on_suspended_kitchens_are_closed(): void
    {
        $this->post->kitchen->update(['status' => 'suspended']);

        $this->getJson("/api/v1/posts/{$this->post->id}/comments")->assertNotFound();
        $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'আছে?'])
            ->assertNotFound();
    }

    public function test_comment_rate_limit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => "কমেন্ট {$i}"])->assertCreated();
        }

        $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/comments", ['body' => 'আরেকটা'])
            ->assertTooManyRequests();
    }
}
