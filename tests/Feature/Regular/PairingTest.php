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

    private function cook(int $max = 8): Kitchen
    {
        return Kitchen::factory()->approved()->create(['area_id' => $this->area->id, 'max_regular_customers' => $max]);
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
        $this->assertSame(7, $kitchen->freeRegularSlots());
    }

    public function test_cook_capacity_is_enforced(): void
    {
        $kitchen = $this->cook(1);
        app(PairCustomer::class)($this->customer(), $kitchen, $this->shahidar, $this->admin);

        $this->expectException(ValidationException::class);
        app(PairCustomer::class)($this->customer(), $kitchen, $this->shahidar, $this->admin);
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
        $this->assertSame(8, $first->fresh()->freeRegularSlots());
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

    public function test_pair_form_lists_same_area_cooks_first_with_free_slots(): void
    {
        $customer = $this->customer();
        $far = Kitchen::factory()->approved()->create(['area_id' => Area::factory()->create()->id, 'display_name' => 'দূরের রান্না']);
        $near = $this->cook(3);

        $options = CustomerResource::kitchenOptions($customer);

        $this->assertSame([$near->id, $far->id], array_keys($options));
        $this->assertStringContainsString('খালি 3/3', $options[$near->id]);
    }
}
