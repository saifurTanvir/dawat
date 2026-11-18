<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\PageWidgets\DeliveryQualityTable;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** F67: scan at the door, wrong-box alert, code on the sticker page, delivery quality per shahidar. */
class DoorScanTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false]);
        $this->travelTo(Carbon::parse('2026-11-18 12:00', 'Asia/Dhaka'));
        $this->area = Area::factory()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
    }

    /** A paired customer's box, picked up with a sticker, code 4321. */
    private function pickedBox(string $name, ?string $sticker = 'AAAA2222'): Order
    {
        $kitchen = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $customer = Customer::factory()->create(['area_id' => $this->area->id]);
        $customer->user->update(['name' => $name]);
        app(PairCustomer::class)($customer, $kitchen, $this->shahidar, User::factory()->admin()->create());
        $post = Post::factory()->create(['kitchen_id' => $kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 3, 'plates_left' => 3]);
        app(BookingPlanner::class)->bookForPost($post);
        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $order->forceFill(['delivery_code_hash' => Hash::make('4321')])->save();
        if ($sticker) {
            Sticker::create(['code' => $sticker, 'batch' => 't']);
        }
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$order->id}/pickup", ['sticker' => $sticker])->assertOk();

        return $order->fresh();
    }

    public function test_scanning_a_sticker_finds_the_box_and_delivers_it(): void
    {
        $order = $this->pickedBox('রহিম');

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/stickers/aaaa 2222')
            ->assertOk()->assertJsonPath('data.id', $order->id)->assertJsonPath('data.customer.name', 'রহিম');

        $this->postJson("/api/v1/shahidar/orders/{$order->id}/deliver", ['sticker' => 'https://dawat.cloud/s/AAAA2222', 'cash_received' => false])
            ->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertSame('shahidar_scan', $order->fresh()->confirm_method);
    }

    public function test_the_wrong_box_is_caught_and_named(): void
    {
        $rahim = $this->pickedBox('রহিম', 'AAAA2222');
        $karim = $this->pickedBox('সেলিম', 'BBBB3333');

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$rahim->id}/deliver", ['sticker' => 'BBBB3333'])
            ->assertStatus(422)
            ->assertJsonFragment(['sticker' => ["⚠️ ভুল বক্স! এটা সেলিম-এর বক্স {$karim->code()}। রহিম-এর বক্স {$rahim->code()} খুঁজে দিন।"]]);
        $this->assertSame(OrderStatus::PickedUp, $rahim->fresh()->status);

        // A blank sticker, and a box picked up with no sticker.
        Sticker::create(['code' => 'CCCC4444', 'batch' => 't']);
        $this->postJson("/api/v1/shahidar/orders/{$rahim->id}/deliver", ['sticker' => 'CCCC4444'])->assertStatus(422);
        $bare = $this->pickedBox('নাসির', null);
        $this->postJson("/api/v1/shahidar/orders/{$bare->id}/deliver", ['sticker' => 'CCCC4444'])
            ->assertStatus(422)->assertJsonFragment(['sticker' => ["বক্স {$bare->code()}-এ পিকআপের সময় স্টিকার লাগানো হয়নি। গ্রাহকের SMS কোড নিন।"]]);
    }

    public function test_scan_refuses_blank_and_other_shahidars_boxes(): void
    {
        $this->pickedBox('রহিম');
        Sticker::create(['code' => 'DDDD5555', 'batch' => 't']);
        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/stickers/DDDD5555')->assertStatus(422);

        $other = User::factory()->create();
        $other->assignRole(Roles::SHAHIDAR);
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/shahidar/stickers/AAAA2222')->assertStatus(422);
    }

    public function test_customer_confirms_with_the_sms_code_without_signing_in_and_guessing_locks(): void
    {
        $order = $this->pickedBox('রহিম');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/stickers/AAAA2222')->assertOk()
            ->assertJsonPath('data.state', 'other')->assertJsonPath('data.can_confirm_with_code', true)
            ->assertDontSee('রহিম');

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/stickers/AAAA2222/confirm', ['code' => '000'.$i])->assertStatus(422);
        }
        $this->postJson('/api/v1/stickers/AAAA2222/confirm', ['code' => '4321'])
            ->assertStatus(422)->assertJsonFragment(['code' => ['অনেকবার ভুল কোড দেওয়া হয়েছে। কোডটা শাহিদারকে বলুন।']]);
        $this->getJson('/api/v1/stickers/AAAA2222')->assertJsonPath('data.can_confirm_with_code', false);

        // The shahidar can still finish with the code.
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$order->id}/deliver", ['code' => '4321'])->assertOk();
    }

    public function test_the_right_code_confirms(): void
    {
        $order = $this->pickedBox('রহিম');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/stickers/AAAA2222/confirm', ['code' => '4321'])->assertOk();
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame('customer_code', $order->confirm_method);
        $this->getJson('/api/v1/stickers/AAAA2222')->assertJsonPath('data.can_confirm_with_code', false);
    }

    public function test_admin_sees_each_shahidars_delivery_quality(): void
    {
        $scanned = $this->pickedBox('রহিম', 'AAAA2222');
        $this->postJson("/api/v1/shahidar/orders/{$scanned->id}/deliver", ['sticker' => 'AAAA2222']);
        $bare = $this->pickedBox('নাসির', null);
        $this->postJson("/api/v1/shahidar/orders/{$bare->id}/deliver", []);

        $this->actingAs(User::factory()->admin()->create(), 'web');
        $this->get('/admin/delivery-quality')->assertOk()->assertSee('ডেলিভারির হিসাব');

        Livewire::test(DeliveryQualityTable::class)
            ->assertCanSeeTableRecords([$this->shahidar])
            ->assertTableColumnStateSet('picked', 2, $this->shahidar)
            ->assertTableColumnStateSet('picked_bare', 1, $this->shahidar)
            ->assertTableColumnStateSet('by_check', 1, $this->shahidar)
            ->assertTableColumnStateSet('by_tap', 1, $this->shahidar)
            ->filterTable('days', 30)
            ->assertTableColumnStateSet('picked', 2, $this->shahidar);

        // Boxes from before the period are not counted.
        $this->travel(10)->days();
        Livewire::test(DeliveryQualityTable::class)->assertTableColumnStateSet('picked', 0, $this->shahidar)
            ->filterTable('days', 30)->assertTableColumnStateSet('picked', 2, $this->shahidar);
    }
}
