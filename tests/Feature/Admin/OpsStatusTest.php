<?php

namespace Tests\Feature\Admin;

use App\Models\OpsHeartbeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F78: reply loops check in; the status page shows running / stopped. */
class OpsStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_check_in_and_page_shows_running(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin-tools/heartbeat', ['channel' => 'messenger', 'sent' => 7, 'pending' => 12, 'note' => 'বুধবারের থ্রেড চলছে'])
            ->assertCreated();

        $this->get('/admin/status')
            ->assertOk()
            ->assertSee('চলছে')
            ->assertSee('বুধবারের থ্রেড চলছে')
            ->assertSee('শুরু হয়নি'); // WhatsApp has not checked in
    }

    public function test_silent_loop_shows_as_stopped(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $beat = OpsHeartbeat::create(['channel' => 'messenger', 'sent' => 1]);
        $beat->forceFill(['created_at' => now()->subMinutes(OpsHeartbeat::STALE_MINUTES + 5)])->save();

        $this->get('/admin/status')->assertOk()->assertSee('থেমে আছে');
    }

    public function test_only_admins_can_check_in_or_see_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/admin-tools/heartbeat', ['channel' => 'messenger'])->assertForbidden();
        $this->get('/admin/status')->assertForbidden();
    }

    public function test_unknown_channel_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin-tools/heartbeat', ['channel' => 'sms'])->assertUnprocessable();
    }

    public function test_dashboard_shows_the_summary_to_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        OpsHeartbeat::create(['channel' => 'messenger', 'sent' => 5, 'pending' => 3]);

        $this->get('/admin')->assertOk()->assertSee('বিস্তারিত দেখুন')->assertSee('চলছে');
    }

    public function test_whatsapp_check_shows_when_it_was_checked_what_came_and_what_was_done(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/admin-tools/heartbeat', [
            'channel' => 'whatsapp', 'found' => 2, 'sent' => 1, 'note' => 'দুপুরের চেক',
            'details' => [
                ['who' => '01616468999', 'said' => 'আসসালামু আলাইকুম', 'did' => 'যোগ দেওয়ার নিয়ম জানানো হলো'],
                ['who' => '01791249228', 'said' => 'ফোন দেবেন', 'did' => 'কল তালিকায় যোগ'],
            ],
        ])->assertCreated();
        $this->postJson('/admin-tools/heartbeat', ['channel' => 'whatsapp', 'found' => 0])->assertCreated();

        $this->get('/admin/status')->assertOk()
            ->assertSee('WhatsApp চেকের হিসাব')
            ->assertSee('কিছু আসেনি')
            ->assertSee('কল তালিকায় যোগ')
            ->assertSee('01616468999');

        // Checked by hand hourly: 40 minutes of silence is still "running", 80 is stopped.
        OpsHeartbeat::query()->update(['created_at' => now()->subMinutes(40)]);
        $this->assertSame('ok', OpsHeartbeat::summary()['whatsapp']['state']);
        OpsHeartbeat::query()->update(['created_at' => now()->subMinutes(80)]);
        $this->assertSame('stopped', OpsHeartbeat::summary()['whatsapp']['state']);
    }
}
