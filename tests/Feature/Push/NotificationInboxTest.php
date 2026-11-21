<?php

namespace Tests\Feature\Push;

use App\Jobs\SendWebPush;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Push\Push;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** F66: every notification is kept in the app's 🔔 list, says what it is about, and opens its page. */
class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_kept_even_when_the_phone_has_push_off(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        Queue::fake();
        $user = User::factory()->create();

        Push::toUser($user, 'নতুন বক্স ১২ · দুপুর', 'রহিমা খালার রান্নাঘর থেকে', '/shahidar', 'order-12');

        Queue::assertNothingPushed();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.title', 'নতুন বক্স ১২ · দুপুর')
            ->assertJsonPath('data.0.section', 'shahidar')
            ->assertJsonPath('data.0.section_label', 'এলাকা ম্যানেজারের কাজ')
            ->assertJsonPath('data.0.url', '/shahidar')
            ->assertJsonPath('data.0.read', false);
    }

    public function test_the_phone_notification_names_the_section_and_opens_through_the_list(): void
    {
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        Queue::fake();
        $user = User::factory()->create();

        Push::toUser($user, 'নতুন বিল', 'নভেম্বর', '/regular', 'bill-1');

        $note = AppNotification::first();
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->notificationId === $note->id && $job->userId === $user->id);
    }

    public function test_a_newer_one_with_the_same_tag_replaces_the_unread_one(): void
    {
        $user = User::factory()->create();
        Push::toUser($user, 'বক্স বুক হয়েছে', '', '/regular', 'order-5');
        Push::toUser($user, 'অন্য কিছু', '', '/orders', 'order-9');
        Push::toUser($user, 'খাবার পথে', '', '/regular', 'order-5');

        $this->assertSame(2, AppNotification::count());
        $this->assertSame('খাবার পথে', AppNotification::latest('id')->value('title'));

        // Once read, the next one is a new row.
        AppNotification::query()->update(['read_at' => now()]);
        Push::toUser($user, 'খাবার কেমন লাগল?', '', '/history', 'order-5');
        $this->assertSame(3, AppNotification::count());
    }

    public function test_open_marks_read_and_returns_the_page_and_read_all_clears(): void
    {
        $user = User::factory()->create();
        Push::toUser($user, 'ক', '', '/cook', 'a');
        Push::toUser($user, 'খ', '', '/admin/kitchens/3', 'b');
        $first = AppNotification::where('title', 'ক')->first();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/notifications/{$first->id}/read")->assertOk()
            ->assertJsonPath('data.url', '/cook')->assertJsonPath('data.read', true)->assertJsonPath('unread', 1);
        $this->getJson('/api/v1/notifications/unread')->assertJsonPath('unread', 1);
        $this->getJson('/api/v1/notifications')->assertJsonPath('data.0.section', 'admin');

        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_nobody_sees_or_opens_someone_elses(): void
    {
        $owner = User::factory()->create();
        Push::toUser($owner, 'গোপন', '', '/cook', null);
        $note = AppNotification::first();

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/notifications/{$note->id}/read")->assertNotFound();
        $this->assertNull($note->fresh()->read_at);
    }

    public function test_old_notifications_are_pruned(): void
    {
        $user = User::factory()->create();
        Push::toUser($user, 'পুরোনো', '', '/', null);
        AppNotification::query()->update(['created_at' => now()->subDays(61)]);
        Push::toUser($user, 'নতুন', '', '/', null);

        $this->artisan('model:prune', ['--model' => [AppNotification::class]])->assertSuccessful();
        $this->assertSame(['নতুন'], AppNotification::pluck('title')->all());
    }

    public function test_sections_by_page(): void
    {
        $this->assertSame('cook', AppNotification::sectionOf('/cook/posts')['key']);
        $this->assertSame('shahidar', AppNotification::sectionOf('/shahidar/kitchens/4')['key']);
        $this->assertSame('admin', AppNotification::sectionOf('/admin/users?tableSearch=017')['key']);
        $this->assertSame('other', AppNotification::sectionOf('/')['key']);
        $this->assertSame('other', AppNotification::sectionOf('/cooking')['key']);
    }
}
