<?php

namespace Tests\Feature\Kitchen;

use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\User;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactNumberVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    /** @var array<string, array{code: string, purpose: OtpPurpose, text: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->kitchen = Kitchen::factory()->approved()->create(['display_name' => 'রহিমা খালার রান্নাঘর']);
        $this->app->instance(OtpSender::class, new class($this->sent) implements OtpSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
            {
                $this->sent[$phone] = ['code' => $code, 'purpose' => $purpose, 'text' => $purpose->message($code, $context)];
            }
        });
    }

    private function asCook(): static
    {
        return $this->actingAs($this->kitchen->user, 'sanctum');
    }

    private function buyerSees(): string
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id]);
        $this->kitchen->update(['allow_call' => true]);
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/v1/posts/{$post->id}/contact", ['type' => 'call'])->json('data.tel');
    }

    public function test_otp_route_confirms_the_number(): void
    {
        $this->asCook()
            ->postJson('/api/v1/kitchen/contact', ['contact_name' => 'করিম ভাই', 'contact_phone' => '+880 1812-345678', 'method' => 'otp'])
            ->assertStatus(202)
            ->assertJsonPath('data.pending_contact.phone', '01812345678')
            ->assertJsonPath('data.pending_contact.method', 'otp')
            ->assertJsonPath('data.contact_phone', null);

        $sms = $this->sent['01812345678'];
        $this->assertSame(OtpPurpose::ContactNumber, $sms['purpose']);
        $this->assertStringContainsString('রহিমা খালার রান্নাঘর', $sms['text']);

        // Not confirmed yet: buyers still reach the cook.
        $this->assertSame('tel:+88'.$this->kitchen->user->phone, $this->buyerSees());

        $this->asCook()
            ->postJson('/api/v1/kitchen/contact/verify', ['code' => $sms['code']])
            ->assertOk()
            ->assertJsonPath('data.contact_phone', '01812345678')
            ->assertJsonPath('data.contact_verified_via', 'otp')
            ->assertJsonPath('data.pending_contact', null);

        $this->assertSame('tel:+8801812345678', $this->buyerSees());
    }

    public function test_wrong_code_keeps_the_old_number(): void
    {
        $this->asCook()->postJson('/api/v1/kitchen/contact', ['contact_name' => 'করিম', 'contact_phone' => '01812345678', 'method' => 'otp']);
        $wrong = $this->sent['01812345678']['code'] === '000000' ? '111111' : '000000';

        $this->asCook()->postJson('/api/v1/kitchen/contact/verify', ['code' => $wrong])->assertUnprocessable();

        $this->assertNull($this->kitchen->fresh()->contact_phone);
    }

    public function test_the_contact_code_cannot_sign_anyone_in_and_vice_versa(): void
    {
        $this->asCook()->postJson('/api/v1/kitchen/contact', ['contact_name' => 'করিম', 'contact_phone' => '01812345678', 'method' => 'otp']);
        $code = $this->sent['01812345678']['code'];
        $this->asCook()->postJson('/api/v1/kitchen/contact/verify', ['code' => $code])->assertOk();

        // Same code again for login: already used.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/verify', ['phone' => '01812345678', 'code' => $code])->assertUnprocessable();
        $this->assertNull(User::where('phone', '01812345678')->first());
    }

    public function test_admin_route_waits_for_an_admin(): void
    {
        $this->asCook()
            ->postJson('/api/v1/kitchen/contact', ['contact_name' => 'করিম ভাই', 'contact_phone' => '01812345678', 'method' => 'admin'])
            ->assertStatus(202)
            ->assertJsonPath('data.pending_contact.method', 'admin');
        $this->assertSame([], $this->sent);

        $this->asCook()->postJson('/api/v1/kitchen/contact/verify', ['code' => '123456'])->assertStatus(409);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->assertActionVisible('approveContact')
            ->callAction('approveContact');

        $fresh = $this->kitchen->fresh();
        $this->assertSame('01812345678', $fresh->contact_phone);
        $this->assertSame('admin', $fresh->contact_verified_via);
        $this->assertFalse($fresh->hasPendingContact());
    }

    public function test_admin_can_reject_a_request(): void
    {
        $this->kitchen->requestContact('01812345678', 'করিম', 'admin');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])->callAction('rejectContact');

        $this->assertFalse($this->kitchen->fresh()->hasPendingContact());
        $this->assertNull($this->kitchen->fresh()->contact_phone);
    }

    public function test_a_new_request_does_not_drop_the_confirmed_number(): void
    {
        $this->kitchen->requestContact('01812345678', 'করিম', 'admin');
        $this->kitchen->confirmPendingContact('admin');

        $this->asCook()->postJson('/api/v1/kitchen/contact', ['contact_name' => 'সালাম', 'contact_phone' => '01912345678', 'method' => 'admin']);

        $this->assertSame('01812345678', $this->kitchen->fresh()->contact_phone);
        $this->assertSame('01912345678', $this->kitchen->fresh()->pending_contact_phone);
    }

    public function test_removing_goes_back_to_the_cooks_own_number(): void
    {
        $this->kitchen->requestContact('01812345678', 'করিম', 'admin');
        $this->kitchen->confirmPendingContact('admin');

        $this->asCook()->deleteJson('/api/v1/kitchen/contact')
            ->assertOk()
            ->assertJsonPath('data.contact_phone', null);

        $this->assertSame('tel:+88'.$this->kitchen->user->phone, $this->buyerSees());
    }

    public function test_validation(): void
    {
        $this->asCook()
            ->postJson('/api/v1/kitchen/contact', ['contact_phone' => $this->kitchen->user->phone, 'method' => 'sms'])
            ->assertJsonValidationErrors(['contact_name', 'contact_phone', 'method']);
    }
}
