<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegularApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Area $area, array $overrides = []): array
    {
        return array_merge([
            'name' => 'নাসির', 'area_id' => $area->id, 'address' => 'বাসা ৭, রোড ২, সেনপাড়া',
            'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ], $overrides);
    }

    public function test_buyer_asks_for_regular_food(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/regular')->assertOk()->assertJsonPath('data', null);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload($area))
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.kitchen', null)
            ->assertJsonPath('data.booking_summary', 'দুপুর · ১ প্লেট · প্রতিদিন');

        $this->assertSame('নাসির', $user->fresh()->name);
        $this->assertSame('app', $user->fresh()->customer->source);
    }

    public function test_home_pin_is_kept_for_admins_and_never_sent_back(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/regular', $this->payload($area, ['lat' => 23.781234, 'lng' => 90.425678]))
            ->assertOk()
            ->assertJsonPath('data.has_location', true);

        $this->assertStringNotContainsString('23.781234', $response->getContent());
        $this->assertStringNotContainsString('"lat"', $response->getContent());
        $this->assertEqualsWithDelta(23.781234, Customer::sole()->location->lat, 0.000001);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/regular', $this->payload($area, ['lat' => 10.5, 'lng' => 90.4]))
            ->assertUnprocessable()->assertJsonValidationErrors(['lat']);
    }

    public function test_validation_messages_are_bangla(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/v1/regular', $this->payload(Area::factory()->create(), ['meal_slots' => [], 'address' => '']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.meal_slots.0', 'অন্তত একটা বেলা বেছে নিন।')
            ->assertJsonPath('errors.address.0', 'ডেলিভারির ঠিকানা লিখুন।');
    }

    public function test_paired_customer_sees_cook_name_and_shahidar_phone_but_no_location(): void
    {
        $area = Area::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload($area))->assertOk();

        $shahidar = User::factory()->create(['name' => 'করিম', 'phone' => '01711000999']);
        $shahidar->assignRole(Roles::SHAHIDAR);
        $shahidar->shahidarAreas()->attach($area);
        $kitchen = Kitchen::factory()->approved()->at(23.80, 90.36)->create(['area_id' => $area->id, 'display_name' => 'রহিমার রান্না']);
        app(PairCustomer::class)($user->fresh()->customer, $kitchen, $shahidar, User::factory()->admin()->create());

        $json = $this->actingAs($user, 'sanctum')->getJson('/api/v1/regular')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.kitchen.name', 'রহিমার রান্না')
            ->assertJsonPath('data.shahidar.phone', '01711000999')
            ->json('data');

        $flat = json_encode($json);
        $this->assertStringNotContainsString('"lat"', $flat);
        $this->assertStringNotContainsString('"lng"', $flat);
        $this->assertStringNotContainsString($kitchen->user->phone, $flat);

        // Area can't be switched while paired.
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload(Area::factory()->create()))
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        // Booking itself can change.
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload($area, ['plates' => 2]))
            ->assertOk()->assertJsonPath('data.plates', 2)->assertJsonPath('data.status', 'active');
    }

    public function test_asking_again_after_stopping_goes_back_to_waiting(): void
    {
        $area = Area::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload($area))->assertOk();
        $user->fresh()->customer->forceFill(['status' => CustomerStatus::Stopped])->save();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/regular', $this->payload($area))
            ->assertOk()->assertJsonPath('data.status', 'waiting');
    }

    public function test_guests_cannot_use_it(): void
    {
        $this->getJson('/api/v1/regular')->assertUnauthorized();
    }
}
