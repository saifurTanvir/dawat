<?php

namespace Tests\Feature\Regular;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Pages\Money;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Billing;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** F39: weekly and monthly bills, part payments and dues. */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Kitchen $kitchen;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // A Saturday in Dhaka.
        $this->travelTo(Carbon::parse('2026-11-07 14:00', 'Asia/Dhaka'));
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->customer = Customer::factory()->create(['billing' => 'weekly']);
        $this->customer->user->update(['name' => 'নাসরিন']);
    }

    private function order(string $date, OrderStatus $status = OrderStatus::Delivered, array $attrs = []): Order
    {
        static $seq = 0;

        return Order::create([
            'service_date' => $date, 'seq' => ++$seq, 'meal_slot' => MealSlot::Lunch,
            'kitchen_id' => $this->kitchen->id, 'customer_id' => $this->customer->id,
            'shahidar_id' => $this->shahidar->id, 'plates' => 1, 'food_paisa' => 15000, 'delivery_fee_paisa' => 2000,
            'status' => $status, ...$attrs,
        ]);
    }

    public function test_a_bill_covers_delivered_and_failed_boxes_only(): void
    {
        $this->order('2026-11-02');
        $this->order('2026-11-03', OrderStatus::Failed);
        $this->order('2026-11-04', OrderStatus::Cancelled);
        $this->order('2026-11-05', OrderStatus::Delivered, ['paid_at' => now()]);

        $bill = app(Billing::class)->issue($this->customer);

        $this->assertSame(2, $bill->orders_count);
        $this->assertSame(34000, $bill->total_paisa);
        $this->assertSame('DW-2611-001', $bill->number);
        $this->assertSame('2026-11-02', $bill->period_from->toDateString());
        $this->assertSame(0, app(Billing::class)->unbilledTotal($this->customer));

        $this->expectException(ValidationException::class);
        app(Billing::class)->issue($this->customer);
    }

    public function test_part_payment_leaves_the_rest_due_and_full_payment_closes_the_boxes(): void
    {
        $a = $this->order('2026-11-02');
        $this->order('2026-11-03');
        $bill = app(Billing::class)->issue($this->customer);

        $bill = app(Billing::class)->pay($bill, 20000, 'bkash');
        $this->assertSame('partial', $bill->status);
        $this->assertSame(14000, $bill->duePaisa());
        $this->assertNull($a->fresh()->paid_at);

        $bill = app(Billing::class)->pay($bill, 14000, 'cash');
        $this->assertSame('paid', $bill->status);
        $this->assertNotNull($a->fresh()->paid_at);
        $this->assertSame('bill', $a->fresh()->payment_method);
        // Paid to Dawat directly, so no shahidar is holding it.
        $this->assertTrue(app(Settlements::class)->shahidarCash()->isEmpty());

        $this->expectException(ValidationException::class);
        app(Billing::class)->pay($bill, 100, 'cash');
    }

    public function test_cannot_overpay_and_void_returns_boxes(): void
    {
        $a = $this->order('2026-11-02');
        $bill = app(Billing::class)->issue($this->customer);

        try {
            app(Billing::class)->pay($bill, 99999, 'cash');
            $this->fail('overpaid');
        } catch (ValidationException) {
        }

        app(Billing::class)->void($bill);
        $this->assertSame('void', $bill->fresh()->status);
        $this->assertNull($a->fresh()->bill_id);
        $this->get('/bill/'.$bill->token)->assertNotFound();
    }

    public function test_saturday_run_bills_weekly_customers_up_to_friday(): void
    {
        $this->order('2026-11-05');
        $this->order('2026-11-07'); // today, goes on next week's bill
        $monthly = Customer::factory()->create(['billing' => 'monthly']);
        Order::create([
            'service_date' => '2026-11-05', 'seq' => 99, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id,
            'customer_id' => $monthly->id, 'plates' => 1, 'food_paisa' => 10000, 'delivery_fee_paisa' => 0, 'status' => OrderStatus::Delivered,
        ]);

        $this->artisan('bills:issue')->assertSuccessful();

        $this->assertSame(1, Bill::count());
        $this->assertSame(1, Bill::first()->orders_count);
        $this->assertSame('2026-11-05', Bill::first()->period_to->toDateString());
        $this->assertSame(0, $monthly->bills()->count());
    }

    public function test_shahidar_does_not_collect_cash_from_a_bill_customer(): void
    {
        $o = $this->order('2026-11-07', OrderStatus::PickedUp);

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$o->id}/deliver")
            ->assertOk()->assertJsonPath('data.paid', false)->assertJsonPath('data.pays_by_bill', true);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$o->id}/paid")->assertUnprocessable();
        $this->assertTrue(app(Settlements::class)->customersOwing()->isEmpty());
    }

    public function test_customer_sees_bills_and_the_public_bill_page(): void
    {
        $this->order('2026-11-02');
        $bill = app(Billing::class)->issue($this->customer);
        $this->order('2026-11-07');

        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/regular/bills')
            ->assertOk()
            ->assertJsonPath('data.billing', 'weekly')
            ->assertJsonPath('data.unbilled_paisa', 17000)
            ->assertJsonPath('data.bills.0.number', $bill->number)
            ->assertJsonPath('data.bills.0.due_paisa', 17000)
            ->assertJsonMissingPath('data.bills.0.token');

        $this->get('/bill/'.$bill->token)->assertOk()->assertSee($bill->number)->assertSee('noindex', false);
    }

    public function test_admin_issues_takes_part_payment_and_sees_dues(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->order('2026-11-02');
        $this->order('2026-11-03');

        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->callAction('issueBill', ['to' => '2026-11-07'])
            ->assertHasNoActionErrors();
        $bill = Bill::firstOrFail();

        $this->get("/admin/bills/{$bill->id}")->assertOk()->assertSee($bill->number)->assertSee('নাসরিন');
        Livewire::test(ViewBill::class, ['record' => $bill->id])
            ->callAction('pay', ['amount_taka' => 100, 'method' => 'bkash', 'note' => 'TX1'])
            ->assertHasNoActionErrors();
        $this->assertSame(24000, $bill->fresh()->duePaisa());

        Livewire::test(ListBills::class)->assertCanSeeTableRecords([$bill])->assertSee('আংশিক পরিশোধ');
        Livewire::test(Money::class)->assertSee('বিলের গ্রাহকদের বাকি')->assertSee('নাসরিন')->assertSee('৳240');
    }
}
