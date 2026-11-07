<?php

namespace Tests\Feature\Regular;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\User;
use App\Support\Roles;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 12:00', 'Asia/Dhaka'));
        $this->actingAs(User::factory()->admin()->create());
    }

    private function order(array $attrs = []): Order
    {
        $customer = Customer::factory()->create(['status' => 'active']);
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $kitchen = Kitchen::factory()->approved()->create();
        $pairing = Pairing::create(['customer_id' => $customer->id, 'kitchen_id' => $kitchen->id, 'shahidar_id' => $shahidar->id, 'started_at' => now()]);

        return Order::create([
            'service_date' => '2026-11-08', 'seq' => Order::max('seq') + 1, 'meal_slot' => MealSlot::Lunch,
            'kitchen_id' => $kitchen->id, 'customer_id' => $customer->id, 'shahidar_id' => $shahidar->id,
            'pairing_id' => $pairing->id, 'plates' => 1, 'food_paisa' => 15000, 'status' => OrderStatus::Booked, ...$attrs,
        ]);
    }

    public function test_orders_list_today_and_cancel(): void
    {
        $today = $this->order();
        $old = $this->order(['service_date' => '2026-11-01']);

        Livewire::test(ManageOrders::class)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$old])
            ->callTableAction('cancel', $today, ['reason' => 'ফোনে বলেছেন']);

        $this->assertSame(OrderStatus::Cancelled, $today->fresh()->status);
        $this->assertSame('admin', $today->fresh()->cancelled_by);
        // Users without a name (most buyers) must not break the filters.
        User::factory()->create(['name' => null])->assignRole(Roles::SHAHIDAR);
        $this->get('/admin/orders')->assertOk();
    }

    public function test_admin_pauses_a_customer(): void
    {
        $order = $this->order();

        Livewire::test(ViewCustomer::class, ['record' => $order->customer_id])
            ->callAction('pause', ['starts_on' => '2026-11-08', 'ends_on' => '2026-11-12', 'reason' => 'বেড়াতে গেছেন'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertCount(1, Customer::find($order->customer_id)->upcomingPauses);
    }

    public function test_area_delivery_fee_in_taka(): void
    {
        Livewire::test(ManageAreas::class)
            ->callAction(CreateAction::class, [
                'name' => 'সেনপাড়া', 'slug' => 'senpara', 'center_lat' => 23.80, 'center_lng' => 90.36,
                'radius_m' => 1000, 'sort_order' => 0, 'is_active' => true, 'delivery_fee_taka' => 20,
            ])->assertHasNoActionErrors();

        $this->assertSame(2000, Area::where('slug', 'senpara')->value('delivery_fee_paisa'));
    }
}
