<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Otp\OtpSender;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_become_buyers(): void
    {
        $sent = [];
        $this->app->instance(OtpSender::class, new class($sent) implements OtpSender
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $code): void
            {
                $this->sent[$phone] = $code;
            }
        });

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => $sent['01712345678']])
            ->assertOk()
            ->assertJsonPath('user.roles', [Roles::BUYER]);
    }

    public function test_make_admin_command_creates_an_admin_with_password(): void
    {
        $this->artisan('dawat:make-admin', ['phone' => '01712345678', 'email' => 'Admin@Dawat.test'])
            ->expectsQuestion('Password for /admin (min 12 characters)', 'a-long-secret-123')
            ->assertSuccessful();

        $admin = User::where('phone', '01712345678')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('admin@dawat.test', $admin->email);
        $this->assertTrue(password_verify('a-long-secret-123', $admin->password));
    }

    public function test_make_admin_rejects_bad_phone(): void
    {
        $this->artisan('dawat:make-admin', ['phone' => '123', 'email' => 'a@b.test'])->assertFailed();
    }
}
