<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Jobs\SendOrderSms;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Orders\Settlements;
use App\Services\Otp\OtpPurpose;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OneOffOrderTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private Kitchen $kitchen;

    private Post $post;

    private User $buyer;

    private User $shahidar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 12:00', 'Asia/Dhaka'));
        $this->area = Area::factory()->create(['delivery_fee_paisa' => 2000]);
        $this->kitchen = Kitchen::factory()->approved()->at(23.8069, 90.3654)->create(['area_id' => $this->area->id]);
        $this->post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'area_id' => $this->area->id, 'meal_slot' => MealSlot::Lunch,
            'price_paisa' => 15000, 'plates_total' => 5, 'plates_left' => 5, 'location' => $this->kitchen->public_location]);
        $this->buyer = User::factory()->create(['name' => 'রাশেদ']);
        $this->shahidar = $this->shahidar($this->area);
    }

    private function shahidar(Area $area): User
    {
        $u = User::factory()->create();
        $u->assignRole(Roles::SHAHIDAR);
        $u->shahidarAreas()->attach($area);

        return $u;
    }

    private function order(array $body = [], ?User $as = null, ?Post $post = null)
    {
        return $this->actingAs($as ?? $this->buyer, 'sanctum')->postJson('/api/v1/posts/'.($post ?? $this->post)->id.'/orders', [
            'plates' => 2, 'receive' => 'delivery', 'address' => 'বাসা ৪, রোড ৭, সেনপাড়া', ...$body,
        ]);
    }

    public function test_delivery_order_books_plates_and_tells_buyer_and_cook(): void
    {
        Queue::fake();

        $this->order()->assertCreated()
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.total_paisa', 32000)
            ->assertJsonPath('data.shahidar.phone', $this->shahidar->phone);

        $order = Order::firstOrFail();
        $this->assertSame('oneoff', $order->kind);
        $this->assertSame($this->buyer->id, $order->buyer_id);
        $this->assertNull($order->customer_id);
        $this->assertSame(3, $this->post->fresh()->plates_left);
        Queue::assertPushed(SendOrderSms::class, fn ($j) => $j->purpose === OtpPurpose::OrderBooked && $j->to === 'recipient' && strlen($j->code) === 4);
        Queue::assertPushed(SendOrderSms::class, fn ($j) => $j->purpose === OtpPurpose::CookNewOrder && $j->to === 'cook');
    }

    public function test_the_cooks_regular_shahidar_is_preferred(): void
    {
        $other = $this->shahidar($this->area);
        $customer = Customer::factory()->create(['area_id' => $this->area->id]);
        app(PairCustomer::class)($customer, $this->kitchen, $other, User::factory()->admin()->create());

        $this->order()->assertCreated();
        $this->assertSame($other->id, Order::where('kind', 'oneoff')->value('shahidar_id'));
    }

    public function test_delivery_needs_a_shahidar_in_the_area(): void
    {
        $this->shahidar->shahidarAreas()->detach();

        $this->getJson("/api/v1/posts/{$this->post->id}")->assertJsonPath('data.order.delivery', false);
        $this->order()->assertUnprocessable()->assertJsonValidationErrors('receive');
        $this->order(['receive' => 'self', 'address' => null])->assertCreated();
    }

    public function test_self_pickup_is_paid_to_the_cook_and_stays_out_of_dawats_cash(): void
    {
        $this->order(['receive' => 'self', 'address' => null])->assertCreated()->assertJsonPath('data.shahidar', null);
        $order = Order::firstOrFail();

        $bookings = $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/bookings')->json('data.slots.0.orders.0');
        $this->assertSame($this->buyer->phone, $bookings['phone']);
        $this->assertTrue($bookings['can_hand']);

        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/orders/{$order->id}/handed")->assertOk();
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertTrue(app(Settlements::class)->cooksDue()->isEmpty());
        $this->assertTrue(app(Settlements::class)->shahidarCash()->isEmpty());
    }

    public function test_limits_and_refusals(): void
    {
        $this->order(['plates' => 6])->assertUnprocessable()->assertJsonValidationErrors('plates');
        $this->order(['address' => ''])->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->order([], $this->kitchen->user)->assertUnprocessable()->assertJsonPath('errors.order.0', 'নিজের রান্না নিজে অর্ডার করা যায় না।');

        $this->order(['plates' => 2])->assertCreated();
        $this->order(['plates' => 2])->assertCreated();
        // Two open orders already.
        $this->order(['plates' => 1])->assertUnprocessable()->assertJsonValidationErrors('order');
        // Only one plate left for someone else.
        $this->order(['plates' => 2], User::factory()->create())->assertUnprocessable()->assertJsonValidationErrors('plates');

        $this->kitchen->update(['allow_order' => false]);
        $this->order(['plates' => 1], User::factory()->create())->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->postJson("/api/v1/posts/{$this->post->id}/orders", ['plates' => 1, 'receive' => 'self']);
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/posts/{$this->post->id}/orders", ['plates' => 1, 'receive' => 'self'])->assertUnauthorized();
    }

    public function test_last_plates_sell_out_the_post(): void
    {
        $this->order(['plates' => 5])->assertCreated();
        $this->assertSame(PostStatus::SoldOut, $this->post->fresh()->status);
        $this->order(['plates' => 1], User::factory()->create())->assertUnprocessable();
    }

    public function test_buyer_cancels_before_pickup(): void
    {
        $this->order()->assertCreated();
        $order = Order::firstOrFail();

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/v1/orders/{$order->id}/cancel")->assertNotFound();
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $this->post->fresh()->plates_left);

        $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/orders')
            ->assertJsonPath('data.0.can_cancel', false)
            ->assertJsonPath('last_address', 'বাসা ৪, রোড ৭, সেনপাড়া');
    }

    public function test_cook_may_decline_within_ten_minutes(): void
    {
        Queue::fake();
        $this->order()->assertCreated();
        $first = Order::firstOrFail();

        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/orders/{$first->id}/decline")->assertOk();
        $this->assertSame('cook', $first->fresh()->cancelled_by);
        Queue::assertPushed(SendOrderSms::class, fn ($j) => $j->purpose === OtpPurpose::OrderDeclined);
        $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/orders')->assertJsonPath('data.0.status_label', 'গৃহিণী দিতে পারেননি');

        $this->order()->assertCreated();
        $second = Order::latest('id')->first();
        $this->travel(11)->minutes();
        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/orders/{$second->id}/decline")->assertUnprocessable();
    }

    public function test_shahidar_delivers_and_buyer_can_confirm_by_scan(): void
    {
        $this->order(['note' => 'গেটে ফোন দেবেন'])->assertCreated();
        $order = Order::firstOrFail();
        Sticker::create(['code' => 'ABCDEFGH', 'batch' => 't']);

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')
            ->assertJsonPath('data.orders.0.kind', 'oneoff')
            ->assertJsonPath('data.orders.0.customer.address', 'বাসা ৪, রোড ৭, সেনপাড়া')
            ->assertJsonPath('data.orders.0.customer.note', 'গেটে ফোন দেবেন');
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$order->id}/pickup", ['sticker' => 'ABCDEFGH'])->assertOk();

        $this->actingAs($this->buyer, 'sanctum')->getJson('/api/v1/stickers/ABCDEFGH')->assertJsonPath('data.state', 'customer');
        $this->actingAs($this->buyer, 'sanctum')->postJson('/api/v1/stickers/ABCDEFGH/received')->assertOk();
        $this->assertSame('customer_scan', $order->fresh()->confirm_method);
    }

    public function test_two_no_shows_pause_ordering_until_an_admin_lifts_it(): void
    {
        foreach (range(1, 2) as $i) {
            $this->order(['plates' => 1])->assertCreated();
            $o = Order::latest('id')->first();
            $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$o->id}/pickup")->assertOk();
            $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$o->id}/fail", ['reason' => 'ফোন ধরেননি'])->assertOk();
        }

        $this->assertFalse($this->buyer->fresh()->canOrder());
        $this->order(['plates' => 1])->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListUsers::class)->callTableAction('allowOrders', $this->buyer->fresh());
        $this->assertTrue($this->buyer->fresh()->canOrder());
    }

    public function test_feed_shows_order_options_without_leaking_anything(): void
    {
        $this->getJson('/api/v1/feed?lat=23.8069&lng=90.3654')
            ->assertJsonPath('data.0.order.enabled', true)
            ->assertJsonPath('data.0.order.delivery', true)
            ->assertJsonPath('data.0.order.delivery_fee_paisa', 2000)
            ->assertJsonPath('data.0.order.max_plates', 5);
    }
}
