<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\Impersonation;
use App\Models\ImpersonationAction;
use App\Models\Kitchen;
use App\Models\Pairing;
use App\Models\User;
use App\Services\Admin\Impersonate;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F51: opening the app as someone else — who may, what is logged, who is told. */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shahidar;

    private Kitchen $kitchen;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->admin = User::factory()->admin()->create(['name' => 'রাশেদ']);
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id]);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
    }

    private function claim(string $url): string
    {
        return $this->postJson('/api/v1/impersonate/claim', ['code' => basename($url)])->assertOk()->json('token');
    }

    public function test_who_may_open_whose_app(): void
    {
        $this->assertTrue(Impersonate::canOpen($this->admin, $this->customer->user));
        $this->assertTrue(Impersonate::canOpen($this->admin, $this->kitchen->user));
        $this->assertFalse(Impersonate::canOpen($this->admin, User::factory()->admin()->create()));

        // A shahidar: their own cooks only, never customers.
        $this->assertTrue(Impersonate::canOpen($this->shahidar, $this->kitchen->user));
        $this->assertFalse(Impersonate::canOpen($this->shahidar, $this->customer->user));
        $this->assertFalse(Impersonate::canOpen($this->shahidar, Kitchen::factory()->approved()->create()->user));
    }

    public function test_the_person_is_told_the_link_works_once_and_changes_are_logged(): void
    {
        Queue::fake();
        $url = app(Impersonate::class)->start($this->admin, $this->kitchen->user);
        $token = $this->claim($url);
        $this->postJson('/api/v1/impersonate/claim', ['code' => basename($url)])->assertUnprocessable();
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->kitchen->user_id && str_contains($j->title, 'দাওয়াত টিম (রাশেদ)'));

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertJsonPath('data.id', $this->kitchen->user_id)
            ->assertJsonPath('data.impersonation.by', 'দাওয়াত টিম (রাশেদ)');

        $this->withToken($token)->putJson('/api/v1/me/contact-hours', ['from' => '10:00', 'to' => '18:00', 'prefer' => 'any'])->assertOk();
        $this->assertSame('কখন ফোন করা যাবে', ImpersonationAction::sole()->label());

        // Their phone's notifications and contact number stay theirs.
        $this->withToken($token)->postJson('/api/v1/push/subscribe', ['endpoint' => 'https://x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertForbidden();

        $this->withToken($token)->postJson('/api/v1/impersonate/end')->assertOk();
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->kitchen->user_id && str_contains($j->body, 'কখন ফোন করা যাবে'));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_sessions_end_after_thirty_minutes(): void
    {
        $token = $this->claim(app(Impersonate::class)->start($this->admin, $this->customer->user));
        $this->travel(31)->minutes();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_shahidar_opens_their_cooks_app_from_the_kitchen_page(): void
    {
        $url = $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/view-as")->assertOk()->json('url');
        $this->app['auth']->forgetGuards();
        $token = $this->claim($url);
        $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('data.impersonation.by', 'শাহিদার করিম');
        $this->assertSame('shahidar', Impersonation::sole()->actor_role);
    }

    public function test_admin_button_and_log(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])->callAction('viewAs')->assertNotified();
        $this->assertSame(1, Impersonation::count());
        $this->postJson('/api/v1/impersonate/claim', ['code' => str_repeat('a', 40)])->assertUnprocessable();
    }
}
