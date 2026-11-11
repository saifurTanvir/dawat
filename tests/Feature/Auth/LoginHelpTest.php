<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\SmsProblems;
use App\Jobs\SendWebPush;
use App\Models\LoginLink;
use App\Models\OtpCode;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Otp\LoginHelp;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** F50: SMS first; when it does not arrive, WhatsApp codes and one-time links. */
class LoginHelpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->admin = User::factory()->admin()->create(['name' => 'রাশেদ']);
    }

    private function useBulkSms(): void
    {
        config(['services.otp.driver' => 'bulksmsbd', 'services.bulksmsbd' => ['base_url' => 'https://bulksmsbd.net', 'api_key' => 'k', 'sender_id' => 's']]);
        // The gateway refuses the second number (1007: no balance).
        Http::fake(fn ($request) => Http::response(['response_code' => str_contains($request->body(), '1722222222') ? 1007 : 202]));
    }

    public function test_every_send_is_logged_and_a_refused_login_code_alerts_admins(): void
    {
        Queue::fake();
        $this->useBulkSms();
        $this->postJson('/api/v1/auth/otp', ['phone' => '01711111111'])->assertStatus(202);
        $this->assertSame('sent', SmsLog::sole()->status);

        $this->postJson('/api/v1/auth/otp', ['phone' => '01722222222'])->assertStatus(503);
        $log = SmsLog::where('phone', '01722222222')->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('1007', $log->error);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->admin->id && str_contains($j->title, 'কোড যায়নি'));
    }

    public function test_asking_again_soon_words_the_sms_differently(): void
    {
        $sent = [];
        $this->app->instance(OtpSender::class, new class($sent) implements OtpSender
        {
            public function __construct(public array &$sent) {}

            public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
            {
                $this->sent[] = $purpose->message($code, $context);
            }
        });
        app(OtpService::class)->request('01733333333');
        $this->travel(2)->minutes();
        app(OtpService::class)->request('01733333333');

        $this->assertStringStartsWith('দাওয়াত লগইন কোড', $sent[0]);
        $this->assertStringStartsWith('আপনার Dawat কোড', $sent[1]);
    }

    public function test_no_code_report_tells_admins_and_gives_dawats_whatsapp(): void
    {
        Queue::fake();
        $this->postJson('/api/v1/auth/no-code', ['phone' => '01744444444'])->assertOk()
            ->assertJsonPath('whatsapp_url', fn ($u) => str_starts_with($u, 'https://wa.me/8801685284290'));
        $this->postJson('/api/v1/auth/no-code', ['phone' => '01744444444'])->assertOk();

        $this->assertSame(1, SmsLog::where('status', 'reported')->count());
        Queue::assertPushed(SendWebPush::class, 1);
    }

    public function test_a_whatsapp_code_signs_in_but_is_marked_not_sms(): void
    {
        $user = User::factory()->create(['phone' => '01755555555']);
        $code = app(LoginHelp::class)->whatsappCode($user, $this->admin);

        $this->postJson('/api/v1/auth/verify', ['phone' => '01755555555', 'code' => $code])->assertOk();
        $user->refresh();
        $this->assertSame('whatsapp', $user->verified_via);
        $this->assertSame($this->admin->id, $user->verified_by);
        $this->assertStringContainsString('SMS-এ যাচাই হয়নি', LoginHelp::label($user));

        // A later SMS sign-in upgrades it.
        $this->travel(2)->minutes();
        app(OtpService::class)->request('01755555555');
        OtpCode::query()->update(['code_hash' => Hash::make('123456')]);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01755555555', 'code' => '123456'])->assertOk();
        $this->assertSame('sms', $user->fresh()->verified_via);
    }

    public function test_a_login_link_works_once_and_expires(): void
    {
        $url = app(LoginHelp::class)->link(User::factory()->create(['phone' => '01766666666']), $this->admin);
        $token = basename($url);

        $this->postJson('/api/v1/auth/link', ['token' => $token])->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertSame('link', User::where('phone', '01766666666')->value('verified_via'));
        $this->postJson('/api/v1/auth/link', ['token' => $token])->assertUnprocessable();

        $url = app(LoginHelp::class)->link(User::where('phone', '01766666666')->first(), $this->admin);
        $this->travel(31)->minutes();
        $this->postJson('/api/v1/auth/link', ['token' => basename($url)])->assertUnprocessable();
        $this->get('/l/'.basename($url))->assertOk()->assertSee('noindex', false);
    }

    public function test_links_are_for_new_numbers_too_but_never_for_admins(): void
    {
        $user = app(LoginHelp::class)->userFor('01777777777');
        $this->assertTrue($user->hasRole('buyer'));

        $this->expectException(ValidationException::class);
        app(LoginHelp::class)->link($this->admin, $this->admin);
    }

    public function test_admin_page_offers_login_help_and_the_qr_page_is_admin_only(): void
    {
        $user = User::factory()->create(['phone' => '01788888888', 'name' => 'নতুন গৃহিণী']);
        SmsLog::create(['phone' => '01788888888', 'user_id' => $user->id, 'purpose' => 'login', 'status' => 'failed', 'error' => 'BulkSMSBD error 1007: balance']);

        $this->actingAs($this->admin);
        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('loginLink')
            ->assertNotified();
        $this->assertSame(1, LoginLink::count());

        Livewire::test(SmsProblems::class)->assertSee('01788888888')->assertSee('balance');

        $this->get(route('admin.login-qr', ['u' => url('/l/abc')]))->assertOk()->assertSee('<svg', false);
        $this->actingAs($user)->get(route('admin.login-qr', ['u' => url('/l/abc')]))->assertForbidden();
    }
}
