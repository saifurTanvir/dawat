<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Pages\Money;
use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F110: an area manager pays the cooks he serves at their door, from his cash, and sees his day in one card. */
class DoorPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manager_pays_a_cook_on_her_day_and_she_confirms(): void
    {
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-24 12:00', 'Asia/Dhaka')); // Tuesday
        $admin = User::factory()->admin()->create();
        $cook = Kitchen::factory()->approved()->create(['pay_cycle' => 'daily']);
        $weekly = Kitchen::factory()->approved()->create(['area_id' => $cook->area_id, 'pay_cycle' => 'weekly', 'pay_weekday' => 4]);
        $karim = User::factory()->create(['name' => 'করিম']);
        $karim->assignRole(Roles::SHAHIDAR);
        $karim->shahidarAreas()->attach($cook->area_id);
        foreach ([$cook, $weekly] as $k) {
            $c = Customer::factory()->create(['area_id' => $k->area_id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 1, 'billing' => 'each']);
            app(PairCustomer::class)($c, $k, $karim, $admin);
            app(BookingPlanner::class)->bookForPost(Post::factory()->create(['kitchen_id' => $k->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 2, 'plates_left' => 2, 'price_paisa' => 15000]));
        }
        foreach (Order::all() as $o) {
            app(Delivery::class)->pickup($o, $karim, null);
            $o = app(Delivery::class)->deliverByShahidar($o->fresh(), $karim, null, null);
            app(Settlements::class)->markPaid($o, $karim);
        }

        $money = $this->actingAs($karim)->getJson('/api/v1/shahidar/today')->assertOk()->json('data.money');
        $this->assertSame(30000, $money['collected_paisa']);
        $rows = collect($money['cooks'])->keyBy('kitchen_id');
        $this->assertTrue($rows[$cook->id]['pay_today']);
        $this->assertFalse($rows[$weekly->id]['pay_today']);
        $due = $rows[$cook->id]['due_paisa'];

        $this->postJson("/api/v1/shahidar/kitchens/{$weekly->id}/pay")->assertUnprocessable(); // not her day
        $after = $this->postJson("/api/v1/shahidar/kitchens/{$cook->id}/pay")->assertOk()->json('data');
        $this->assertSame($due, $after['paid_cooks_paisa']);
        $this->assertSame(30000 - $due, $after['to_dawat_paisa']);
        $this->assertSame(30000 - $due, app(Settlements::class)->cashHeldBy($karim));
        $this->assertStringContainsString('পেয়েছেন?', AppNotification::where('user_id', $cook->user_id)->latest('id')->first()->title);

        // The cook confirms in her app; until then admins see it.
        $this->actingAs($admin);
        Livewire::test(Money::class)->assertSee('নিশ্চিত বাকি');
        $pay = $this->actingAs($cook->user)->getJson('/api/v1/my/pay-schedule')->json('data');
        $this->assertSame($due, $pay['payouts'][0]['amount_paisa']);
        $this->postJson("/api/v1/my/payouts/{$pay['payouts'][0]['id']}/confirm", ['got' => true])->assertOk()->assertJsonPath('data.payouts', []);
        $this->actingAs($weekly->user)->postJson("/api/v1/my/payouts/{$pay['payouts'][0]['id']}/confirm", ['got' => false])->assertNotFound();
        $this->assertSame(OrderStatus::Delivered, Order::first()->status);
    }
}
