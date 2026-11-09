<?php

namespace Tests\Feature\Auth;

use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevAnyCodeTest extends TestCase
{
    use RefreshDatabase;

    private function environment(string $env): void
    {
        $this->app['env'] = $env;
        config(['services.otp.any_code' => true]);
    }

    public function test_on_a_developer_machine_any_code_signs_in(): void
    {
        $this->environment('local');

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])
            ->assertAccepted()
            ->assertJsonPath('dev_any_code', true);

        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => '123456'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_any_code_also_confirms_a_contact_number_locally(): void
    {
        $this->environment('local');
        $kitchen = Kitchen::factory()->approved()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->postJson('/api/v1/kitchen/contact', ['contact_name' => 'করিম', 'contact_phone' => '01812345678', 'method' => 'otp'])
            ->assertStatus(202);
        $this->actingAs($kitchen->user, 'sanctum')
            ->postJson('/api/v1/kitchen/contact/verify', ['code' => '000000'])
            ->assertOk()
            ->assertJsonPath('data.contact_phone', '01812345678');
    }

    public function test_a_code_of_the_wrong_length_still_fails_locally(): void
    {
        $this->environment('local');
        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678']);

        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => '123'])->assertUnprocessable();
    }

    public function test_production_ignores_the_setting(): void
    {
        $this->environment('production');

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertJsonPath('dev_any_code', false);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => '123456'])->assertUnprocessable();
        $this->assertNull(User::where('phone', '01712345678')->first());
    }

    /** Staging (behind an nginx password, no real SMS) accepts any code, like local. */
    public function test_staging_accepts_any_code(): void
    {
        $this->environment('staging');

        $this->postJson('/api/v1/auth/otp', ['phone' => '01712345678'])->assertJsonPath('dev_any_code', true);
        $this->postJson('/api/v1/auth/verify', ['phone' => '01712345678', 'code' => '123456'])->assertOk();
    }
}
