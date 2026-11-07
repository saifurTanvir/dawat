<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Services\Otp\BulkSmsBdSender;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BulkSmsBdSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.otp.driver' => 'bulksmsbd',
            'services.bulksmsbd' => ['base_url' => 'https://bulksmsbd.net', 'api_key' => 'k-123', 'sender_id' => '8809617'],
        ]);
    }

    public function test_driver_is_bound(): void
    {
        $this->assertInstanceOf(BulkSmsBdSender::class, app(OtpSender::class));
    }

    public function test_sends_bangla_otp_to_880_number(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202, 'success_message' => 'SMS Submitted Successfully'])]);

        app(OtpSender::class)->send('01712345678', '482913', OtpPurpose::Login);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://bulksmsbd.net/api/smsapi'
            && $r->method() === 'POST'
            && $r['api_key'] === 'k-123'
            && $r['senderid'] === '8809617'
            && $r['number'] === '8801712345678'
            && str_contains($r['message'], '482913')
            && str_contains($r['message'], 'দাওয়াত'));
    }

    public function test_gateway_error_is_logged_without_phone_or_code(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 1007, 'error_message' => 'Insufficient Balance'])]);
        Log::spy();

        try {
            app(OtpSender::class)->send('01712345678', '482913');
            $this->fail('Expected failure');
        } catch (OtpDeliveryFailedException) {
        }

        Log::shouldHaveReceived('error')->withArgs(function ($msg, $ctx = []) {
            $all = $msg.json_encode($ctx);

            return ($ctx['reason'] ?? null) === 'insufficient balance'
                && ! str_contains($all, '482913') && ! str_contains($all, '1712345678');
        });
    }

    public function test_failed_send_returns_bangla_503_and_allows_immediate_retry(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::sequence()
            ->push(['response_code' => 1032], 200)
            ->push(['response_code' => 202], 200)]);

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'এখন কোড পাঠানো যাচ্ছে না। একটু পরে আবার চেষ্টা করুন।');
        $this->assertSame(0, OtpCode::count());

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertStatus(202);
        $this->assertSame(1, OtpCode::count());
    }

    public function test_missing_key_fails_without_calling(): void
    {
        config(['services.bulksmsbd.api_key' => null]);
        Http::fake();

        $this->expectException(OtpDeliveryFailedException::class);
        try {
            app(OtpSender::class)->send('01712345678', '482913');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_sms_test_command(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);

        $this->artisan('dawat:sms-test', ['phone' => '01712345678'])->assertSuccessful();
        $this->artisan('dawat:sms-test', ['phone' => '12345'])->assertFailed();
    }
}
