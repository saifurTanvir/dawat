<?php

namespace Tests\Feature\Geo;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Geo\Geocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** F62: a home location from a link (short ones too), current location or an address. */
class LocationInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('<html><meta content="https://www.google.com/maps/place/Badda/@23.7812345,90.4256789,17z"></html>'),
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'হোসেন মার্কেট, উত্তর বাড্ডা, ঢাকা', 'lat' => '23.7865', 'lon' => '90.4262'],
                ['display_name' => 'Somewhere in India', 'lat' => '28.6', 'lon' => '77.2'],
            ]),
            'maps.googleapis.com/*' => Http::response(['results' => [
                ['formatted_address' => 'Gulshan 2, Dhaka', 'geometry' => ['location' => ['lat' => 23.7946, 'lng' => 90.4143]]],
            ]]),
        ]);
    }

    public function test_reads_coordinates_full_links_and_short_share_links(): void
    {
        $g = app(Geocoder::class);
        $this->assertEqualsWithDelta(23.78, $g->point('23.78, 90.42')->lat, 0.001);
        $this->assertEqualsWithDelta(23.7801, $g->point('https://maps.google.com/?q=23.7801,90.4253')->lat, 0.0001);
        $this->assertEqualsWithDelta(23.7812345, $g->point('দেখুন https://maps.app.goo.gl/AbCd123 এখানে')->lat, 0.000001);
        $this->assertNull($g->point('https://example.com/my-home'));
        $this->assertNull($g->point('আমার বাসা'));
    }

    public function test_address_search_uses_openstreetmap_without_a_key_and_google_with_one(): void
    {
        $results = app(Geocoder::class)->search('হোসেন মার্কেট বাড্ডা');
        $this->assertCount(1, $results); // the place outside Bangladesh is dropped
        $this->assertStringContainsString('উত্তর বাড্ডা', $results[0]['label']);

        config(['services.google_maps.key' => 'test-key']);
        $results = app(Geocoder::class)->search('Gulshan 2 Dhaka');
        $this->assertSame('Gulshan 2, Dhaka', $results[0]['label']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'maps.googleapis.com') && str_contains($request->url(), 'country%3ABD'));
    }

    public function test_app_and_admin_endpoints(): void
    {
        $this->getJson('/api/v1/geocode?q=badda')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/geocode?q='.urlencode('হোসেন মার্কেট বাড্ডা'))
            ->assertOk()->assertJsonPath('data.0.lat', 23.7865);

        $this->actingAs($user)->get('/admin-tools/geocode?q=badda')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson('/admin-tools/geocode?q='.urlencode('https://maps.app.goo.gl/AbCd123'))
            ->assertOk()->assertJsonPath('data.0.label', 'লিংকের জায়গা');
    }

    public function test_admin_adds_a_cook_and_a_customer_with_a_short_link_and_pins_a_customer(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $area = Area::factory()->create(['is_active' => true]);

        Livewire::test(Dashboard::class)
            ->callAction('addCook', ['phone' => '01711223344', 'name' => 'সুমা', 'display_name' => 'সুমার রান্না', 'area_id' => $area->id, 'where' => 'https://maps.app.goo.gl/AbCd123'])
            ->assertHasNoActionErrors();
        $this->assertEqualsWithDelta(23.7812345, Kitchen::sole()->location->lat, 0.000001);

        Livewire::test(Dashboard::class)
            ->callAction('addCustomer', [
                'phone' => '01912345678', 'name' => 'জামান', 'area_id' => $area->id, 'address' => 'বাসা ১২, রোড ৩',
                'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2], 'where' => '23.7850, 90.4250',
            ])
            ->assertHasNoActionErrors();
        $customer = Customer::sole();
        $this->assertEqualsWithDelta(23.785, $customer->location->lat, 0.0001);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pin', ['where' => 'কোথাও একটা'])
            ->assertHasActionErrors(['where']);
        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pin', ['where' => 'https://maps.app.goo.gl/AbCd123'])
            ->assertHasNoActionErrors();
        $this->assertEqualsWithDelta(23.7812345, $customer->fresh()->location->lat, 0.000001);
    }
}
