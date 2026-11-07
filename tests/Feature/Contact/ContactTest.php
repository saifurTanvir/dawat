<?php

namespace Tests\Feature\Contact;

use App\Enums\ContactType;
use App\Enums\KitchenStatus;
use App\Enums\PostStatus;
use App\Models\ContactEvent;
use App\Models\Post;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = Post::factory()->create(['title' => 'খিচুড়ি']);
        $this->post->kitchen->update(['allow_call' => true, 'allow_message' => true]);
    }

    private User $buyer;

    private function asBuyer(): static
    {
        $this->buyer ??= User::factory()->create();

        return $this->actingAs($this->buyer, 'sanctum');
    }

    public function test_guests_must_sign_in_to_get_a_number(): void
    {
        foreach (['call', 'message'] as $type) {
            $this->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => $type])
                ->assertUnauthorized()
                ->assertJsonMissingPath('data');
        }
        $this->assertSame(0, ContactEvent::count());

        // Sharing stays open to everyone.
        $this->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'share'])->assertOk();
    }

    public function test_message_gives_a_prefilled_whatsapp_link(): void
    {
        $phone = $this->post->kitchen->user->phone;

        $response = $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message'])
            ->assertOk();

        $url = $response->json('data.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/88'.$phone.'?text=', $url);
        $this->assertStringContainsString(rawurlencode('খিচুড়ি'), $url);
        $this->assertStringContainsString(rawurlencode(url('/p/'.$this->post->id)), $url);

        $event = ContactEvent::sole();
        $this->assertSame(ContactType::Message, $event->type);
        $this->assertSame($this->buyer->id, $event->viewer_id);
    }

    public function test_call_returns_a_tel_link_and_logs_the_signed_in_buyer(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'call'])
            ->assertOk()
            ->assertJsonPath('data.tel', 'tel:+88'.$this->post->kitchen->user->phone);

        $this->assertSame($buyer->id, ContactEvent::sole()->viewer_id);
    }

    public function test_shahidar_number_is_used_when_set(): void
    {
        $this->post->kitchen->update(['contact_phone' => '01812345678', 'contact_name' => 'করিম ভাই']);

        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'call'])
            ->assertJsonPath('data.tel', 'tel:+8801812345678')
            ->assertJsonPath('data.name', 'করিম ভাই');
    }

    public function test_disallowed_buttons_are_refused_and_not_logged(): void
    {
        $this->post->kitchen->update(['allow_call' => false]);

        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'call'])->assertForbidden();
        $this->assertSame(0, ContactEvent::count());
    }

    public function test_unavailable_posts_do_not_reveal_numbers(): void
    {
        $soldOut = Post::factory()->create(['status' => PostStatus::SoldOut, 'plates_left' => 0]);
        $past = Post::factory()->create(['available_until' => now()->subMinute()]);
        $suspended = Post::factory()->create();
        $suspended->kitchen->update(['status' => KitchenStatus::Suspended]);

        foreach ([$soldOut, $past, $suspended] as $post) {
            $this->asBuyer()->postJson("/api/v1/posts/{$post->id}/contact", ['type' => 'message'])
                ->assertStatus(410)
                ->assertJsonMissingPath('data');
        }
    }

    public function test_share_is_logged_without_a_number(): void
    {
        $this->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'share'])
            ->assertOk()
            ->assertJsonPath('data.share_url', url('/p/'.$this->post->id))
            ->assertJsonMissingPath('data.tel');
    }

    public function test_the_qr_that_brought_the_buyer_is_credited(): void
    {
        $qr = QrCode::factory()->create();

        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message', 'qr' => strtoupper($qr->code)]);
        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message', 'qr' => 'unknown1']);

        $this->assertSame([$qr->id, null], ContactEvent::orderBy('id')->pluck('qr_code_id')->all());
    }

    public function test_comment_type_cannot_be_faked_here(): void
    {
        $this->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'comment'])
            ->assertJsonValidationErrors(['type']);
    }

    public function test_a_blocked_cook_gets_no_more_calls(): void
    {
        $this->post->kitchen->user->update(['status' => 'blocked']);

        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'call'])->assertStatus(410);
        $this->getJson('/api/v1/posts/'.$this->post->id)->assertOk();
    }

    public function test_each_buyer_has_a_daily_limit(): void
    {
        $this->asBuyer();
        foreach (range(1, 30) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.'.intdiv($i, 9).'.'.$i])
                ->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message'])->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message'])
            ->assertTooManyRequests();
    }

    public function test_number_harvesting_is_rate_limited(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'share']);
        }

        $this->asBuyer()->postJson("/api/v1/posts/{$this->post->id}/contact", ['type' => 'message'])->assertTooManyRequests();
    }
}
