<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\LoginHelp;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F57: a new number signs up without waiting for an SMS; an existing account still needs the code. */
class SignupWithoutCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_number_gets_in_at_once_and_stays_unverified(): void
    {
        $response = $this->postJson('/api/v1/auth/start', ['phone' => '০১৭১২৩৪৫৬৭৮'])
            ->assertCreated()
            ->assertJsonPath('mode', 'signed_up')
            ->assertJsonStructure(['token', 'user']);

        $user = User::where('phone', '01712345678')->sole();
        $this->assertTrue($user->hasRole(Roles::BUYER));
        $this->assertNull($user->phone_verified_at);
        $this->assertSame(LoginHelp::UNVERIFIED, $user->verified_via);
        $this->assertSame(0, OtpCode::count()); // no SMS at all

        $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.phone', '01712345678');
    }

    public function test_an_existing_account_is_never_opened_without_the_code(): void
    {
        $owner = User::factory()->create(['phone' => '01712345678']);

        $response = $this->postJson('/api/v1/auth/start', ['phone' => '01712345678'])
            ->assertAccepted()
            ->assertJsonPath('mode', 'code')
            ->assertJsonMissingPath('token');

        $this->assertSame(1, OtpCode::where('phone', '01712345678')->count());
        $this->assertSame(1, User::where('phone', '01712345678')->count());
        $this->assertTrue($owner->fresh()->is(User::where('phone', '01712345678')->first()));

        // Also an account that was itself opened without a code.
        $this->postJson('/api/v1/auth/start', ['phone' => '01812345678'])->assertCreated();
        $this->postJson('/api/v1/auth/start', ['phone' => '01812345678'])->assertAccepted()->assertJsonPath('mode', 'code')->assertJsonMissingPath('token');
    }

    public function test_new_accounts_without_a_code_are_limited_per_connection(): void
    {
        foreach (range(1, OtpController::SIGNUPS_PER_IP_PER_DAY) as $i) {
            $this->postJson('/api/v1/auth/start', ['phone' => '0171000000'.$i])->assertCreated();
        }
        $this->postJson('/api/v1/auth/start', ['phone' => '01719999999'])->assertTooManyRequests();
        $this->assertNull(User::where('phone', '01719999999')->first());
    }

    public function test_admin_verifies_on_a_call_before_approving_a_cook_or_pairing_a_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $cookUser = User::factory()->create(['phone_verified_at' => null, 'verified_via' => LoginHelp::UNVERIFIED]);
        $kitchen = Kitchen::factory()->create(['user_id' => $cookUser->id]);

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])
            ->callAction('approve', ['called_and_verified' => false])
            ->assertHasActionErrors(['called_and_verified']);
        $this->assertFalse($kitchen->fresh()->isApproved());

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])
            ->callAction('approve', ['called_and_verified' => true])
            ->assertHasNoActionErrors();
        $this->assertTrue($kitchen->fresh()->isApproved());
        $this->assertSame('call', $cookUser->fresh()->verified_via);
        $this->assertNotNull($cookUser->fresh()->phone_verified_at);
        $this->assertSame($admin->id, $cookUser->fresh()->verified_by);

        // Pairing a customer who signed up without a code.
        $area = Area::factory()->create();
        $kitchen->update(['area_id' => $area->id]);
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $shahidar->shahidarAreas()->attach($area);
        $buyer = User::factory()->create(['phone_verified_at' => null, 'verified_via' => LoginHelp::UNVERIFIED]);
        $customer = Customer::factory()->create(['user_id' => $buyer->id, 'area_id' => $area->id]);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pair', ['kitchen_id' => $kitchen->id, 'shahidar_id' => $shahidar->id])
            ->assertHasActionErrors(['called_and_verified']);
        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pair', ['kitchen_id' => $kitchen->id, 'shahidar_id' => $shahidar->id, 'called_and_verified' => true])
            ->assertHasNoActionErrors();
        $this->assertSame('call', $buyer->fresh()->verified_via);
    }

    public function test_verified_people_are_not_asked_again(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $kitchen = Kitchen::factory()->create(['user_id' => User::factory()->create(['phone_verified_at' => now(), 'verified_via' => 'sms'])->id]);

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])->callAction('approve')->assertHasNoActionErrors();
        $this->assertTrue($kitchen->fresh()->isApproved());
    }
}
