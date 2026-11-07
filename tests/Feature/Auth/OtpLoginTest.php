<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    /** Codes "sent" during the test, keyed by phone. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(OtpSender::class, new class($this->sent) implements OtpSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $code): void
            {
                $this->sent[$phone] = $code;
            }
        });
    }

    public function test_full_sign_in_creates_a_user_and_returns_a_token(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '+880 1712-345678'])
            ->assertAccepted()
            ->assertJsonPath('resend_after', 60);

        $code = $this->sent['01712345678'];

        $response = $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('user.phone', '01712345678');

        $user = User::where('phone', '01712345678')->firstOrFail();
        $this->assertNotNull($user->phone_verified_at);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_the_code_is_stored_hashed(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertAccepted();

        $this->assertNotSame($this->sent['01712345678'], OtpCode::first()->code_hash);
    }

    public function test_existing_user_signs_in_without_duplicate(): void
    {
        User::factory()->create(['phone' => '01812345678']);

        $this->postJson('/api/v1/auth/otp', ['phone' => '01812345678']);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01812345678', 'code' => $this->sent['01812345678']])->assertOk();

        $this->assertSame(1, User::where('phone', '01812345678')->count());
    }

    public function test_wrong_code_is_rejected_and_attempts_are_limited(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $right = $this->sent['01712345678'];
        $wrong = $right === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $wrong])
                ->assertUnprocessable();
        }

        // After five wrong tries even the right code no longer works.
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $right])
            ->assertUnprocessable();
    }

    public function test_a_code_works_only_once(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $code = $this->sent['01712345678'];

        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $code])->assertOk();
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $code])->assertUnprocessable();
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $code = $this->sent['01712345678'];

        $this->travel(6)->minutes();

        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $code])->assertUnprocessable();
    }

    public function test_resending_too_soon_is_throttled(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertAccepted();
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])
            ->assertTooManyRequests()
            ->assertJsonStructure(['retry_after']);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertAccepted();
    }

    public function test_only_the_newest_code_works(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $first = $this->sent['01712345678'];
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $second = $this->sent['01712345678'];

        if ($first !== $second) {
            $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $first])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $second])->assertOk();
    }

    public function test_invalid_phone_gets_a_bangla_validation_message(): void
    {
        $this->postJson('/api/v1/auth/otp', ['phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_blocked_user_cannot_sign_in_or_use_old_token(): void
    {
        $user = User::factory()->blocked()->create(['phone' => '01712345678']);
        $token = $user->createToken('old')->plainTextToken;

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $this->sent['01712345678']])
            ->assertForbidden();

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('pwa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_can_set_their_name(): void
    {
        $user = User::factory()->create(['name' => null]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', ['name' => 'রহিমা খাতুন'])
            ->assertOk()
            ->assertJsonPath('data.name', 'রহিমা খাতুন');
    }

    public function test_daily_ip_cap_on_otp(): void
    {
        config(['dawat.otp.per_ip_per_day' => 3]);

        foreach (range(1, 3) as $i) {
            $this->travel(2)->minutes();
            $this->postJson('/api/v1/auth/otp', ['phone' => sprintf('0171%07d', $i)])->assertAccepted();
        }

        $this->travel(2)->minutes();
        $this->postJson('/api/v1/auth/otp', ['phone' => '01799999999'])->assertTooManyRequests();
    }

    public function test_app_wide_hourly_cap_on_sending_codes(): void
    {
        config(['dawat.otp.global_per_hour' => 2]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('/api/v1/auth/otp', ['phone' => '01711111111'])->assertAccepted();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/api/v1/auth/otp', ['phone' => '01722222222'])->assertAccepted();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->postJson('/api/v1/auth/otp', ['phone' => '01733333333'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'এখন অনেকে একসাথে কোড চাইছেন। কয়েক মিনিট পরে আবার চেষ্টা করুন।');
    }

    public function test_ip_rate_limit_applies_to_otp_endpoints(): void
    {
        RateLimiter::clear('otp');

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/auth/otp', ['phone' => sprintf('0171%07d', $i)]);
        }

        $this->postJson('/api/v1/auth/otp', ['phone' => '01799999999'])->assertTooManyRequests();
    }
}
