<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Services\Regular\PairingSuggester;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Area $area;

    private User $shahidar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->area = Area::factory()->create();
        $this->shahidar = $this->makeShahidar($this->area);
    }

    private function makeShahidar(Area $area): User
    {
        $user = User::factory()->create();
        $user->assignRole(Roles::SHAHIDAR);
        $user->shahidarAreas()->attach($area);

        return $user;
    }

    private function cook(array $attrs = []): Kitchen
    {
        return Kitchen::factory()->approved()->create(['area_id' => $this->area->id, ...$attrs]);
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['area_id' => $this->area->id]);
    }

    public function test_pairing_activates_the_customer(): void
    {
        $customer = $this->customer();
        $kitchen = $this->cook();

        $pairing = app(PairCustomer::class)($customer, $kitchen, $this->shahidar, $this->admin);

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertTrue($customer->fresh()->pairing->is($pairing));
        $this->assertFalse($kitchen->isFreeForRegular());
        $this->assertTrue($kitchen->isFreeForRegular($customer));
    }

    public function test_one_cook_one_customer(): void
    {
        $kitchen = $this->cook();
        $first = $this->customer();
        app(PairCustomer::class)($first, $kitchen, $this->shahidar, $this->admin);

        try {
            app(PairCustomer::class)($this->customer(), $kitchen, $this->shahidar, $this->admin);
            $this->fail('A cook with a customer must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($first->user->name, $e->errors()['kitchen_id'][0]);
        }

        // The same customer may still change only their shahidar.
        $other = $this->makeShahidar($this->area);
        app(PairCustomer::class)($first, $kitchen, $other, $this->admin, 'শাহিদার বদল');
        $this->assertSame($other->id, $first->fresh()->pairing->shahidar_id);
    }

    public function test_a_shahidar_serves_many(): void
    {
        foreach (range(1, 3) as $i) {
            app(PairCustomer::class)($this->customer(), $this->cook(), $this->shahidar, $this->admin);
        }
        $this->assertSame(3, $this->shahidar->shahidarPairings()->whereNull('ended_at')->count());
    }

    public function test_shahidar_must_serve_the_customers_area(): void
    {
        $elsewhere = $this->makeShahidar(Area::factory()->create());

        $this->expectException(ValidationException::class);
        app(PairCustomer::class)($this->customer(), $this->cook(), $elsewhere, $this->admin);
    }

    public function test_unapproved_cook_is_refused(): void
    {
        $pending = Kitchen::factory()->create(['area_id' => $this->area->id]);

        $this->expectException(ValidationException::class);
        app(PairCustomer::class)($this->customer(), $pending, $this->shahidar, $this->admin);
    }

    public function test_changing_cook_needs_a_reason_and_keeps_history(): void
    {
        $customer = $this->customer();
        $first = $this->cook();
        $second = $this->cook();
        app(PairCustomer::class)($customer, $first, $this->shahidar, $this->admin);

        try {
            app(PairCustomer::class)($customer, $second, $this->shahidar, $this->admin);
            $this->fail('Reason should be required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        app(PairCustomer::class)($customer, $second, $this->shahidar, $this->admin, 'ঝাল বেশি লাগে');

        $customer->refresh();
        $this->assertSame($second->id, $customer->pairing->kitchen_id);
        $this->assertCount(2, $customer->pairings);
        $old = $customer->pairings->firstWhere('kitchen_id', $first->id);
        $this->assertNotNull($old->ended_at);
        $this->assertSame('ঝাল বেশি লাগে', $old->end_reason);
        $this->assertTrue($first->fresh()->isFreeForRegular());
    }

    public function test_stopping_ends_the_pairing(): void
    {
        $customer = $this->customer();
        app(PairCustomer::class)($customer, $this->cook(), $this->shahidar, $this->admin);

        PairCustomer::stop($customer, $this->admin, 'শহরের বাইরে চলে গেছেন');

        $this->assertSame(CustomerStatus::Stopped, $customer->fresh()->status);
        $this->assertNull($customer->fresh()->pairing);
    }

    public function test_admin_creates_and_pairs_a_customer(): void
    {
        $this->actingAs($this->admin);
        $kitchen = $this->cook();

        Livewire::test(ListCustomers::class)
            ->callAction(CreateAction::class, [
                'phone' => '01912345678', 'name' => 'জামান সাহেব', 'area_id' => $this->area->id,
                'address' => 'বাসা ১২, রোড ৩, ফ্ল্যাট ৪বি', 'meal_slots' => ['lunch', 'dinner'], 'plates' => 2,
                'weekdays' => [0, 1, 2, 3, 4], 'note' => 'ঝাল কম',
            ])
            ->assertHasNoActionErrors();

        $customer = Customer::firstOrFail();
        $this->assertSame('01912345678', $customer->user->phone);
        $this->assertSame(['lunch', 'dinner'], $customer->meal_slots);
        $this->assertSame('admin', $customer->source);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pair', ['kitchen_id' => $kitchen->id, 'shahidar_id' => $this->shahidar->id])
            ->assertHasNoActionErrors();

        $this->assertSame($kitchen->id, $customer->fresh()->pairing->kitchen_id);
        $this->get("/admin/customers/{$customer->id}")->assertOk()->assertSee($kitchen->display_name);
        $this->get('/admin/customers')->assertOk();
    }

    public function test_pair_form_lists_only_free_cooks_nearest_first_and_suggests_the_nearest(): void
    {
        $customer = $this->customer();
        $customer->location = GeoPoint::make(23.7800, 90.4200);
        $customer->save();

        $far = $this->cook(['display_name' => 'দূরের রান্না', 'location' => GeoPoint::make(23.8000, 90.4200)]);   // ~2.2 km
        $near = $this->cook(['display_name' => 'কাছের রান্না', 'location' => GeoPoint::make(23.7870, 90.4200)]);  // ~780 m
        $taken = $this->cook(['display_name' => 'জোড়া রান্না', 'location' => GeoPoint::make(23.7801, 90.4200)]);
        app(PairCustomer::class)($this->customer(), $taken, $this->shahidar, $this->admin);
        Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => GeoPoint::make(23.7802, 90.4200)]); // pending

        $options = CustomerResource::kitchenOptions($customer);

        $this->assertSame([$near->id, $far->id], array_keys($options));
        $this->assertStringContainsString('★ সাজেশন', $options[$near->id]);
        $this->assertStringContainsString('প্রায় ৮০০ মিটার', $options[$near->id]);
        $this->assertStringContainsString('প্রায় ২.২ কিমি', $options[$far->id]);
    }

    public function test_without_a_pin_distance_is_from_the_area_middle(): void
    {
        $this->area->update(['center' => GeoPoint::make(23.7800, 90.4200)]);
        $customer = $this->customer();
        $this->cook(['display_name' => 'সুমা', 'location' => GeoPoint::make(23.7870, 90.4200)]);

        $line = app(PairingSuggester::class)->suggestionLine($customer->fresh());
        $this->assertStringContainsString('সুমা', $line);
        $this->assertStringContainsString('এলাকার মাঝখান থেকে আন্দাজ', $line);
    }

    public function test_pair_form_starts_on_the_suggestion_and_records_it(): void
    {
        $this->actingAs($this->admin);
        $customer = $this->customer();
        $customer->location = GeoPoint::make(23.7800, 90.4200);
        $customer->save();
        $near = $this->cook(['location' => GeoPoint::make(23.7810, 90.4200)]);
        $far = $this->cook(['location' => GeoPoint::make(23.8000, 90.4200)]);
        $busy = $this->makeShahidar($this->area);
        app(PairCustomer::class)($this->customer(), $this->cook(), $busy, $this->admin);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->mountAction('pair')
            ->assertActionDataSet(['kitchen_id' => $near->id, 'shahidar_id' => $this->shahidar->id])
            ->setActionData(['kitchen_id' => $far->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $pairing = $customer->fresh()->pairing;
        $this->assertSame($far->id, $pairing->kitchen_id);
        $this->assertSame($near->id, $pairing->suggested_kitchen_id);
        $this->assertSame($this->shahidar->id, $pairing->suggested_shahidar_id);
        $this->assertFalse($pairing->followedSuggestion());
    }

    public function test_admin_sets_a_home_pin_from_a_map_link(): void
    {
        $this->actingAs($this->admin);
        $customer = $this->customer();

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pin', ['where' => 'https://www.google.com/maps/place/x/@23.7812345,90.4256789,17z'])
            ->assertHasNoActionErrors();
        $this->assertEqualsWithDelta(23.7812345, $customer->fresh()->location->lat, 0.000001);

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->callAction('pin', ['where' => 'আমার বাসা'])
            ->assertHasActionErrors(['where']);
    }
}
