<?php

namespace Tests\Feature\Money;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Pages\CookFeesPage;
use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Filament\Resources\Kitchens\Pages\EditKitchen;
use App\Models\AppSetting;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\CookFees;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CookFeesTest extends TestCase
{
    use RefreshDatabase;

    private function global(string $pType, int $pValue, string $dType, int $dValue): void
    {
        AppSetting::put(CookFees::KEY, [
            'platform_fee_type' => $pType, 'platform_fee_value' => $pValue,
            'delivery_cut_type' => $dType, 'delivery_cut_value' => $dValue,
        ]);
    }

    private function order(Kitchen $kitchen, array $attrs = []): Order
    {
        static $seq = 0;
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);

        return Order::create([
            'service_date' => '2026-11-14', 'seq' => ++$seq, 'meal_slot' => MealSlot::Lunch,
            'kitchen_id' => $kitchen->id, 'customer_id' => Customer::factory()->create()->id,
            'shahidar_id' => $shahidar->id, 'plates' => 1, 'food_paisa' => 10000, 'delivery_fee_paisa' => 2000,
            'status' => OrderStatus::Delivered, ...$attrs,
        ]);
    }

    public function test_the_users_example_100_taka_less_10_and_10(): void
    {
        $this->global('fixed', 1000, 'fixed', 1000);
        $kitchen = Kitchen::factory()->approved()->create();

        $this->assertSame(['price' => 10000, 'platform' => 1000, 'delivery' => 1000, 'cook' => 8000], app(CookFees::class)->breakdown($kitchen, 10000));
        // Fixed amounts are per plate; percent is of the whole price.
        $this->assertSame(16000, app(CookFees::class)->breakdown($kitchen, 10000, plates: 2)['cook']);
        $this->global('percent', 1000, 'percent', 500);
        $this->assertSame(['price' => 15000, 'platform' => 1500, 'delivery' => 750, 'cook' => 12750], app(CookFees::class)->breakdown($kitchen, 15000));
    }

    public function test_cook_beats_area_beats_everyone_part_by_part(): void
    {
        $this->global('fixed', 1000, 'fixed', 1000);
        $area = Area::factory()->create(['platform_fee_type' => 'percent', 'platform_fee_value' => 500]);
        $kitchen = Kitchen::factory()->approved()->create(['area_id' => $area->id]);

        $rules = app(CookFees::class)->rules($kitchen);
        $this->assertSame(['type' => 'percent', 'value' => 500, 'source' => 'area'], $rules['platform']);
        $this->assertSame('global', $rules['delivery']['source']);

        $kitchen->update(['delivery_cut_type' => 'fixed', 'delivery_cut_value' => 0]);
        $rules = app(CookFees::class)->rules($kitchen->fresh());
        $this->assertSame(['type' => 'fixed', 'value' => 0, 'source' => 'kitchen'], $rules['delivery']);
        $this->assertSame(9500, app(CookFees::class)->breakdown($kitchen->fresh(), 10000)['cook']);
    }

    public function test_fees_are_fixed_on_the_box_and_settlement_takes_both_off(): void
    {
        $this->global('fixed', 1000, 'fixed', 1000);
        $kitchen = Kitchen::factory()->approved()->create();
        $box = $this->order($kitchen);
        $this->order($kitchen, ['receive' => 'self', 'payment_method' => 'cash_to_cook']);

        $this->assertSame(1000, $box->platform_fee_paisa);
        $this->assertSame(1000, $box->cook_delivery_paisa);

        // A later change of rule does not touch boxes already made.
        $this->global('fixed', 3000, 'fixed', 3000);
        $box->update(['status' => OrderStatus::Delivered]);
        $this->assertSame(1000, $box->fresh()->platform_fee_paisa);

        $due = app(Settlements::class)->cooksDue()->first();
        $this->assertSame(1, $due['orders']); // self pickup is paid to her directly
        $this->assertSame(['gross' => 10000, 'commission' => 1000, 'delivery' => 1000, 'payable' => 8000], array_intersect_key($due, array_flip(['gross', 'commission', 'delivery', 'payable'])));

        $s = app(Settlements::class)->payCook($kitchen, User::factory()->admin()->create());
        $this->assertSame(1000, (int) $s->commission_paisa);
        $this->assertSame(1000, (int) $s->delivery_paisa);
        $this->assertSame(8000, (int) $s->amount_paisa);
    }

    public function test_self_pickup_takes_nothing_and_cut_never_exceeds_price(): void
    {
        $this->global('fixed', 5000, 'fixed', 9000);
        $kitchen = Kitchen::factory()->approved()->create();

        $self = $this->order($kitchen, ['receive' => 'self', 'payment_method' => 'cash_to_cook']);
        $this->assertSame([0, 0], [$self->platform_fee_paisa, $self->cook_delivery_paisa]);

        $b = app(CookFees::class)->breakdown($kitchen, 10000);
        $this->assertSame([5000, 5000, 0], [$b['platform'], $b['delivery'], $b['cook']]);
    }

    public function test_cook_app_gets_the_rules_and_admin_sets_everyone_and_one_cook(): void
    {
        $this->global('fixed', 0, 'fixed', 0);
        $cook = User::factory()->create();
        $kitchen = Kitchen::factory()->approved()->create(['user_id' => $cook->id]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(CookFeesPage::class)
            ->callAction('edit', ['platform_fee_type' => 'fixed', 'platform_fee_value' => 10, 'delivery_cut_type' => 'percent', 'delivery_cut_value' => 7.5])
            ->assertHasNoActionErrors();
        $this->assertEquals(['platform_fee_type' => 'fixed', 'platform_fee_value' => 1000, 'delivery_cut_type' => 'percent', 'delivery_cut_value' => 750], AppSetting::get(CookFees::KEY));
        $this->get('/admin/cook-fees')->assertOk()->assertSee('৳৮২.৫');

        Livewire::test(EditKitchen::class, ['record' => $kitchen->id])
            ->fillForm(['platform_fee_type' => 'percent', 'platform_fee_value' => 5])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['percent', 500], [$kitchen->fresh()->platform_fee_type, (int) $kitchen->fresh()->platform_fee_value]);

        $this->actingAs($cook, 'sanctum')->getJson('/api/v1/kitchen')
            ->assertOk()
            ->assertJsonPath('data.fees.platform', ['type' => 'percent', 'value' => 500, 'text' => '৫%'])
            ->assertJsonPath('data.fees.delivery.type', 'percent')
            ->assertJsonPath('data.fees.delivery.text', '৭.৫%');
    }

    public function test_admin_sets_an_area_rule_from_the_area_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $area = Area::factory()->create(['delivery_cut_type' => 'fixed', 'delivery_cut_value' => 1500]);

        Livewire::test(ManageAreas::class)
            ->mountTableAction('edit', $area)
            ->assertTableActionDataSet(['delivery_cut_type' => 'fixed', 'delivery_cut_value' => 15, 'platform_fee_type' => ''])
            ->setTableActionData(['platform_fee_type' => 'fixed', 'platform_fee_value' => 12])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $area->refresh();
        $this->assertSame(['fixed', 1200, 'fixed', 1500], [$area->platform_fee_type, (int) $area->platform_fee_value, $area->delivery_cut_type, (int) $area->delivery_cut_value]);
    }

    public function test_dawat_fee_is_flat_ten_taka_by_default_and_can_be_a_percent(): void
    {
        $kitchen = Kitchen::factory()->approved()->create();
        $rules = app(CookFees::class)->rules($kitchen);
        $this->assertSame(['type' => 'fixed', 'value' => 1000, 'source' => 'global'], $rules['platform']);
        $this->assertSame(9000, app(CookFees::class)->breakdown($kitchen, 10000)['cook']);

        $kitchen->update(['platform_fee_type' => 'percent', 'platform_fee_value' => 500]);
        $this->assertSame(9500, app(CookFees::class)->breakdown($kitchen->fresh(), 10000)['cook']);
    }
}
