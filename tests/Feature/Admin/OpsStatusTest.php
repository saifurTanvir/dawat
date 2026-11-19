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
}
