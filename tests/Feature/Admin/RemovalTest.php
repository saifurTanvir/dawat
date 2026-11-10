<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Shahidars\Pages\ManageShahidars;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\Settlement;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** F42: test or mistaken set-ups can be undone; settled money cannot vanish. */
class RemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shahidar;

    private Kitchen $kitchen;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 09:00', 'Asia/Dhaka'));
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
        $area = Area::first();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($area);
        $this->kitchen = Kitchen::factory()->approved()->create(['area_id' => $area->id]);
        $this->customer = Customer::factory()->create(['area_id' => $area->id, 'status' => CustomerStatus::Active]);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
    }

    private function order(string $date, OrderStatus $status, array $attrs = []): Order
    {
        static $seq = 0;

        return Order::create([
            'service_date' => $date, 'seq' => ++$seq, 'meal_slot' => MealSlot::Lunch,
            'kitchen_id' => $this->kitchen->id, 'customer_id' => $this->customer->id, 'shahidar_id' => $this->shahidar->id,
            'plates' => 1, 'food_paisa' => 15000, 'delivery_fee_paisa' => 2000, 'status' => $status, ...$attrs,
        ]);
    }

    public function test_unpair_sends_the_customer_back_to_waiting_and_cancels_upcoming_boxes(): void
    {
        $today = $this->order('2026-11-10', OrderStatus::Booked);
        $past = $this->order('2026-11-09', OrderStatus::Delivered);

        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->callAction('unpair', ['reason' => 'টেস্ট'])->assertHasNoActionErrors();

        $this->assertSame(CustomerStatus::Waiting, $this->customer->fresh()->status);
        $this->assertNull($this->customer->fresh()->pairing);
        $this->assertSame(OrderStatus::Cancelled, $today->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $past->fresh()->status);
    }

    public function test_a_stopped_customer_can_be_started_again(): void
    {
        $this->customer->update(['status' => CustomerStatus::Stopped]);
        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])->callAction('restart');
        $this->assertSame(CustomerStatus::Waiting, $this->customer->fresh()->status);
    }

    public function test_a_test_customer_is_deleted_with_boxes_and_optionally_the_account(): void
    {
        $this->order('2026-11-09', OrderStatus::Delivered, ['paid_at' => now(), 'payment_method' => 'cash', 'collected_by' => $this->shahidar->id]);
        $userId = $this->customer->user_id;

        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->callAction('deleteCustomer', ['with_account' => true, 'confirm' => 'ভুল'])
            ->assertHasActionErrors(['confirm']);
        $this->assertNotNull($this->customer->fresh());

        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->callAction('deleteCustomer', ['with_account' => true, 'confirm' => 'মুছুন'])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $this->assertNull(Customer::find($this->customer->id));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Pairing::count());
        $this->assertNull(User::find($userId));
        // The kitchen and shahidar stay.
        $this->assertNotNull($this->kitchen->fresh());
        $this->assertTrue($this->shahidar->fresh()->isShahidar());
    }

    public function test_settled_money_blocks_a_delete(): void
    {
        $settlement = Settlement::create(['kind' => 'cook', 'kitchen_id' => $this->kitchen->id, 'orders_count' => 1, 'gross_paisa' => 15000, 'commission_paisa' => 0, 'amount_paisa' => 15000]);
        $this->order('2026-11-09', OrderStatus::Delivered, ['cook_settlement_id' => $settlement->id]);

        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->assertActionVisible('deleteCustomer')
            ->callAction('deleteCustomer');
        $this->assertNotNull($this->customer->fresh());

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])->callAction('deleteKitchen');
        $this->assertNotNull($this->kitchen->fresh());
    }

    public function test_deleting_a_kitchen_frees_its_customers(): void
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id]);
        $this->order('2026-11-10', OrderStatus::Booked);

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('deleteKitchen', ['with_account' => false, 'confirm' => 'মুছুন'])
            ->assertHasNoActionErrors();

        $this->assertNull(Kitchen::find($this->kitchen->id));
        $this->assertNull(Post::find($post->id));
        $this->assertSame(0, Order::count());
        $this->assertSame(CustomerStatus::Waiting, $this->customer->fresh()->status);
        $this->assertNotNull(User::find($this->kitchen->user_id));
    }

    public function test_removing_a_shahidar_unpairs_their_customers_unless_cash_is_held(): void
    {
        $o = $this->order('2026-11-09', OrderStatus::Delivered, ['paid_at' => now(), 'payment_method' => 'cash', 'collected_by' => $this->shahidar->id]);

        Livewire::test(ManageShahidars::class)->callTableAction('removeRole', $this->shahidar);
        $this->assertTrue($this->shahidar->fresh()->isShahidar());

        $o->delete();
        Livewire::test(ManageShahidars::class)->callTableAction('removeRole', $this->shahidar);
        $this->assertFalse($this->shahidar->fresh()->isShahidar());
        $this->assertSame(CustomerStatus::Waiting, $this->customer->fresh()->status);
    }

    public function test_a_whole_account_can_be_deleted_but_not_your_own(): void
    {
        $cook = $this->kitchen->user;
        Livewire::test(ViewUser::class, ['record' => $cook->id])
            ->callAction('deleteUser', ['confirm' => 'মুছুন'])->assertHasNoActionErrors();
        $this->assertNull(User::find($cook->id));
        $this->assertNull(Kitchen::find($this->kitchen->id));

        Livewire::test(ViewUser::class, ['record' => $this->admin->id])->assertActionHidden('deleteUser');
    }
}
