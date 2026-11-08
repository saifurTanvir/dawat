<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderSms;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Otp\OtpPurpose;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PriceLimitTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 11:30', 'Asia/Dhaka'));
        $this->area = Area::factory()->create(['delivery_fee_paisa' => 2000]);
        $this->kitchen = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->shahidar = User::factory()->create();
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
    }

    private function regular(?int $maxTaka): Customer
    {
        $c = Customer::factory()->create(['area_id' => $this->area->id, 'max_price_paisa' => $maxTaka ? $maxTaka * 100 : null]);
        app(PairCustomer::class)($c, $this->kitchen, $this->shahidar, User::factory()->admin()->create());

        return $c->fresh();
    }

    private function postAt(int $taka, int $plates = 4): Post
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'price_paisa' => $taka * 100, 'plates_total' => $plates, 'plates_left' => $plates]);
        app(BookingPlanner::class)->bookForPost($post);

        return $post->fresh();
    }

    public function test_within_the_limit_books_as_before(): void
    {
        $this->regular(180);
        $this->postAt(150);

        $this->assertSame(OrderStatus::Booked, Order::first()->status);
    }

    public function test_above_the_limit_holds_the_plate_and_asks(): void
    {
        Queue::fake();
        $this->regular(180);
        $post = $this->postAt(200);

        $order = Order::first();
        $this->assertSame(OrderStatus::Asking, $order->status);
        $this->assertNull($order->delivery_code_hash);
        $this->assertSame(0, $order->delivery_fee_paisa);
        $this->assertTrue($order->ask_until->equalTo(now()->addMinutes(45)));
        $this->assertSame(3, $post->plates_left);
        Queue::assertPushed(SendOrderSms::class, fn ($j) => $j->purpose === OtpPurpose::PriceAsk && $j->code === '');
    }

    public function test_ask_sms_names_price_and_limit(): void
    {
        Log::spy();
        $this->regular(180);
        $this->postAt(200);

        Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, 'প্লেট ৳২০০ (আপনার সীমা ৳১৮০)') && str_contains($m, '৪৫ মিনিটের'));
    }

    public function test_customer_accepts_and_gets_a_delivery_code(): void
    {
        $customer = $this->regular(180);
        $this->postAt(200);
        $order = Order::first();
        Queue::fake();

        $this->actingAs($customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertJsonPath('data.today.0.can_accept', true);
        $this->actingAs($customer->user, 'sanctum')->postJson("/api/v1/regular/orders/{$order->id}/accept")
            ->assertOk()->assertJsonPath('data.today.0.status', 'booked');

        $order->refresh();
        $this->assertNotNull($order->delivery_code_hash);
        $this->assertSame(2000, $order->delivery_fee_paisa);
        Queue::assertPushed(SendOrderSms::class, fn ($j) => $j->purpose === OtpPurpose::OrderBooked && strlen($j->code) === 4);

        $this->actingAs($customer->user, 'sanctum')->postJson("/api/v1/regular/orders/{$order->id}/accept")->assertUnprocessable();
    }

    public function test_shahidar_accepts_or_declines_on_the_customers_behalf(): void
    {
        $this->regular(180);
        $this->regular(150);
        $post = $this->postAt(200);
        [$a, $b] = Order::orderBy('id')->get()->all();

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')
            ->assertJsonPath('data.orders.0.status', 'asking')
            ->assertJsonPath('data.orders.0.max_price_paisa', 18000);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$a->id}/accept")->assertOk()->assertJsonPath('data.status', 'booked');
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$b->id}/skip")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(3, $post->fresh()->plates_left);
    }

    public function test_no_answer_in_time_releases_the_plate(): void
    {
        $this->regular(180);
        $post = $this->postAt(200);

        $this->travel(44)->minutes();
        $this->artisan('orders:expire-asks')->assertSuccessful();
        $this->assertSame(OrderStatus::Asking, Order::first()->status);

        $this->travel(2)->minutes();
        $this->artisan('orders:expire-asks')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, Order::first()->status);
        $this->assertSame('system', Order::first()->cancelled_by);
        $this->assertSame(4, $post->fresh()->plates_left);
    }

    public function test_held_plates_cannot_be_sold_and_cannot_be_picked_up(): void
    {
        $this->regular(180);
        $post = $this->postAt(200, 2);
        $order = Order::first();

        $this->actingAs($this->kitchen->user, 'sanctum')->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 2])->assertUnprocessable();
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/orders/{$order->id}/pickup")->assertUnprocessable();
    }

    public function test_cook_sees_the_lowest_limit_and_customer_sets_it(): void
    {
        $customer = $this->regular(180);
        $this->regular(150);
        $this->regular(null);

        $lunch = collect($this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/bookings')->json('data.slots'))->firstWhere('meal_slot', 'lunch');
        $this->assertSame(15000, $lunch['price_limit_paisa']);
        $this->assertSame(2, $lunch['limited_customers']);

        $this->actingAs($customer->user, 'sanctum')->putJson('/api/v1/regular', [
            'name' => 'নাসির', 'area_id' => $this->area->id, 'address' => 'বাসা ৭, রোড ২', 'meal_slots' => ['lunch'],
            'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'max_price_taka' => 200,
        ])->assertOk()->assertJsonPath('data.max_price_paisa', 20000)->assertJsonPath('data.booking_summary', 'দুপুর · ১ প্লেট · প্রতিদিন · প্লেট সর্বোচ্চ ৳২০০');
    }
}
