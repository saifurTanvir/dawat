<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Stickers\Pages\ManageStickers;
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

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Customer $customer;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false]);
        $this->travelTo(Carbon::parse('2026-11-08 12:00', 'Asia/Dhaka'));
        $area = Area::factory()->create();
        $this->kitchen = Kitchen::factory()->approved()->create(['area_id' => $area->id, 'pickup_address' => 'বাসা ৫, রোড ১']);
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($area);
        $this->customer = Customer::factory()->create(['area_id' => $area->id, 'address' => 'ফ্ল্যাট ৩এ, বাসা ৯']);
        app(PairCustomer::class)($this->customer, $this->kitchen, $this->shahidar, User::factory()->admin()->create());
    }

    private function order(): Order
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 3, 'plates_left' => 3]);
        app(BookingPlanner::class)->bookForPost($post);

        return Order::firstOrFail();
    }

    private function asShahidar()
    {
        return $this->actingAs($this->shahidar, 'sanctum');
    }

    public function test_shahidar_day_lists_pickup_and_delivery_addresses(): void
    {
        $order = $this->order();

        $this->asShahidar()->getJson('/api/v1/shahidar/today')
            ->assertOk()
            ->assertJsonPath('data.orders.0.code', $order->code())
            ->assertJsonPath('data.orders.0.kitchen.address', 'বাসা ৫, রোড ১')
            ->assertJsonPath('data.orders.0.customer.address', 'ফ্ল্যাট ৩এ, বাসা ৯')
            ->assertJsonMissingPath('data.orders.0.kitchen.location');
    }

    public function test_waiting_list_shows_customers_whose_cook_has_not_posted(): void
    {
        $this->asShahidar()->getJson('/api/v1/shahidar/today')
            ->assertJsonPath('data.orders', [])
            ->assertJsonPath('data.waiting.0.meal_slot_label', 'দুপুর')
            ->assertJsonPath('data.waiting.0.kitchen', $this->kitchen->display_name);
    }

    public function test_only_shahidars_and_only_their_own_boxes(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/shahidar/today')->assertForbidden();

        $other = User::factory()->create();
        $other->assignRole(Roles::SHAHIDAR);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertNotFound();
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/shahidar/today')->assertJsonPath('data.orders', []);
    }

    public function test_pickup_binds_a_sticker_once(): void
    {
        $order = $this->order();
        $sticker = Sticker::create(['code' => 'ABCDEFGH', 'batch' => 't']);

        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup", ['sticker' => 'https://dawat.cloud/s/abcdefgh'])
            ->assertOk()->assertJsonPath('data.status', 'picked_up')->assertJsonPath('data.sticker', 'ABCD EFGH');
        $this->assertSame($order->id, $sticker->fresh()->order_id);

        // Already picked up; and the sticker can't go on a second box.
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertUnprocessable();
        $second = Order::create([...$order->only(['meal_slot', 'kitchen_id', 'shahidar_id', 'plates']), 'customer_id' => Customer::factory()->create()->id, 'service_date' => '2026-11-08', 'seq' => 99, 'status' => OrderStatus::Booked]);
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$second->id}/pickup", ['sticker' => 'ABCD EFGH'])
            ->assertUnprocessable()->assertJsonPath('errors.sticker.0', 'এই স্টিকার অন্য বক্সে লাগানো হয়েছে। নতুন একটা নিন।');
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$second->id}/pickup", ['sticker' => 'ZZZZ'])
            ->assertUnprocessable()->assertJsonValidationErrors('sticker');
    }

    public function test_deliver_with_the_customers_code(): void
    {
        $order = $this->order();
        $order->forceFill(['delivery_code_hash' => Hash::make('4821')])->save();
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertOk();

        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/deliver", ['code' => '1111'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/deliver", ['code' => '4821'])
            ->assertOk()->assertJsonPath('data.status', 'delivered')->assertJsonPath('data.confirm_method', 'shahidar_code');
    }

    public function test_deliver_by_tap_is_marked_for_checking(): void
    {
        $order = $this->order();
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/deliver")->assertUnprocessable(); // not picked up
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertOk();

        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/deliver")
            ->assertOk()->assertJsonPath('data.confirm_method', 'shahidar_tap');
    }

    public function test_failed_delivery_needs_a_reason(): void
    {
        $order = $this->order();
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertOk();

        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/fail")->assertUnprocessable();
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/fail", ['reason' => 'বাসায় কেউ নেই'])
            ->assertOk()->assertJsonPath('data.status', 'failed');
    }

    public function test_shahidar_skips_for_a_customer_who_called(): void
    {
        $order = $this->order();

        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/skip")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(3, Post::first()->plates_left);
    }

    public function test_scanning_a_sticker(): void
    {
        $order = $this->order();
        Sticker::create(['code' => 'ABCDEFGH', 'batch' => 't']);
        Sticker::create(['code' => 'JKMNPQRS', 'batch' => 't']);
        $this->asShahidar()->postJson("/api/v1/shahidar/orders/{$order->id}/pickup", ['sticker' => 'ABCDEFGH'])->assertOk();

        // A stranger, signed in or not, learns nothing.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/stickers/ABCDEFGH')->assertOk()->assertExactJson(['data' => ['state' => 'other']]);
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/stickers/abcd-efgh')->assertExactJson(['data' => ['state' => 'other']]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/stickers/JKMNPQRS')->assertExactJson(['data' => ['state' => 'blank']]);

        // The customer sees their box and confirms.
        $customer = $this->customer->user;
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/stickers/ABCDEFGH')
            ->assertJsonPath('data.state', 'customer')->assertJsonPath('data.order.can_confirm', true);
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/stickers/ABCDEFGH/received')->assertOk();
        $this->assertSame('customer_scan', $order->fresh()->confirm_method);
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/stickers/ABCDEFGH/received')->assertUnprocessable();

        // Someone else cannot confirm it.
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v1/stickers/ABCDEFGH/received')->assertNotFound();
    }

    public function test_admin_prints_a_sticker_sheet(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageStickers::class)->callAction('newSheet', ['pages' => 1])->assertHasNoActionErrors();

        $this->assertSame(32, Sticker::count());
        $batch = Sticker::value('batch');
        $this->get("/admin/stickers/sheet/{$batch}")->assertOk()->assertSee('<svg', false)->assertSee(Sticker::first()->displayCode());
        $this->get('/admin/stickers')->assertOk();
        $this->get('/admin/orders')->assertOk();
    }

    public function test_sticker_codes_avoid_look_alike_characters(): void
    {
        foreach (range(1, 50) as $i) {
            $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', Sticker::newCode());
        }
    }
}
