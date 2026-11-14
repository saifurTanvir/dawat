<?php

namespace Tests\Feature\Regular;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Pages\Money;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Money\CookFees;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 14:00', 'Asia/Dhaka'));
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->kitchen = Kitchen::factory()->approved()->create(['display_name' => 'রহিমার রান্না']);
    }

    private function order(OrderStatus $status, array $attrs = []): Order
    {
        static $seq = 0;

        return Order::create([
            'service_date' => '2026-11-08', 'seq' => ++$seq, 'meal_slot' => MealSlot::Lunch,
            'kitchen_id' => $this->kitchen->id, 'customer_id' => Customer::factory()->create()->id,
            'shahidar_id' => $this->shahidar->id, 'plates' => 1, 'food_paisa' => 15000, 'delivery_fee_paisa' => 2000,
            'status' => $status, ...$attrs,
        ]);
    }

    public function test_deliver_marks_cash_unless_told_not_to(): void
    {
        $a = $this->order(OrderStatus::PickedUp);
        $b = $this->order(OrderStatus::PickedUp);

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$a->id}/deliver")
            ->assertOk()->assertJsonPath('data.paid', true);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$b->id}/deliver", ['cash_received' => false])
            ->assertOk()->assertJsonPath('data.paid', false);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$b->id}/paid")
            ->assertOk()->assertJsonPath('data.paid', true);

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')->assertJsonPath('data.cash_held_paisa', 34000);
    }

    public function test_cash_cannot_be_marked_before_delivery(): void
    {
        $o = $this->order(OrderStatus::PickedUp);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$o->id}/paid")->assertUnprocessable();
    }

    public function test_cook_is_owed_for_delivered_and_failed_boxes_less_commission(): void
    {
        AppSetting::put(CookFees::KEY, ['platform_fee_type' => 'percent', 'platform_fee_value' => 1000, 'delivery_cut_type' => 'fixed', 'delivery_cut_value' => 0]);
        $this->order(OrderStatus::Delivered, ['paid_at' => now(), 'collected_by' => $this->shahidar->id]);
        $this->order(OrderStatus::Failed);
        $this->order(OrderStatus::Cancelled);
        $this->order(OrderStatus::Booked);

        $due = app(Settlements::class)->cooksDue()->first();
        $this->assertSame(2, $due['orders']);
        $this->assertSame(30000, $due['gross']);
        $this->assertSame(3000, $due['commission']);
        $this->assertSame(27000, $due['payable']);

        $owing = app(Settlements::class)->customersOwing();
        $this->assertCount(1, $owing);
        $this->assertSame(OrderStatus::Failed, $owing->first()->status);
    }

    public function test_admin_settles_with_shahidar_and_cook(): void
    {
        $this->order(OrderStatus::Delivered, ['paid_at' => now(), 'collected_by' => $this->shahidar->id]);
        $this->order(OrderStatus::Delivered, ['paid_at' => now(), 'collected_by' => $this->shahidar->id]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/money')->assertOk()->assertSee('করিম')->assertSee('৳340')->assertSee('রহিমার রান্না')->assertSee('৳300');

        Livewire::test(Money::class)
            ->callAction('receiveCash', ['note' => 'সন্ধ্যায় হাতে'], ['shahidar' => $this->shahidar->id])
            ->assertHasNoActionErrors()
            ->callAction('payCook', ['note' => 'বিকাশে'], ['kitchen' => $this->kitchen->id])
            ->assertHasNoActionErrors();

        $this->assertSame(34000, (int) Settlement::where('kind', 'shahidar')->value('amount_paisa'));
        $this->assertSame(30000, (int) Settlement::where('kind', 'cook')->value('amount_paisa'));
        $this->assertSame(0, Order::whereNull('cook_settlement_id')->count());
        $this->assertTrue(app(Settlements::class)->cooksDue()->isEmpty());
        $this->assertTrue(app(Settlements::class)->shahidarCash()->isEmpty());

        // Nothing left: settling again is refused.
        $this->expectException(ValidationException::class);
        app(Settlements::class)->payCook($this->kitchen, User::first());
    }
}
