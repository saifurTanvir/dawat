<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\CustomerPause;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Delivery;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F45: late pairings get a box, the reason when there is none, and the customer follows the box. */
class LateBookingTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 11:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->admin = User::factory()->admin()->create();
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 1]);
        $this->customer->user->update(['name' => 'কল্যাণ']);
    }

    private function livePost(int $platesLeft = 4): Post
    {
        return Post::factory()->create([
            'kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'status' => PostStatus::Live,
            'plates_total' => 4, 'plates_left' => $platesLeft, 'price_paisa' => 12000, 'title' => 'ভাত, ডাল',
            'published_at' => now()->subHour(), 'available_until' => now()->addHours(2),
        ]);
    }

    private function pair(): void
    {
        app(PairCustomer::class)($this->customer, $this->kitchen, $this->shahidar, $this->admin);
    }

    public function test_pairing_after_the_post_books_a_box_from_it(): void
    {
        Queue::fake();
        $post = $this->livePost();
        $this->pair();

        $order = Order::where('customer_id', $this->customer->id)->sole();
        $this->assertSame(OrderStatus::Booked, $order->status);
        $this->assertSame($post->id, $order->post_id);
        $this->assertSame(3, $post->fresh()->plates_left);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->shahidar->id && str_contains($j->body, 'পোস্টের পরে জুটি'));

        // Not twice.
        app(BookingPlanner::class)->bookLate($this->customer);
        $this->assertSame(1, Order::count());
    }

    public function test_no_plates_left_gives_a_reason_instead(): void
    {
        $this->livePost(0);
        $this->pair();

        $this->assertSame(0, Order::count());
        $this->assertSame('পোস্ট এসেছে, কিন্তু ফিডে যথেষ্ট প্লেট বাকি নেই (০ বাকি)', app(BookingPlanner::class)->todayReasons($this->customer)[0]['line']);
    }

    public function test_reasons_cover_the_usual_cases(): void
    {
        $planner = app(BookingPlanner::class);
        $this->assertSame('গ্রাহক এখন "জুটির অপেক্ষায়" — জুটি চালু নেই', $planner->todayReasons($this->customer)[0]['line']);

        $this->pair();
        $this->assertSame('গৃহিণী এখনো পোস্ট দেননি', $planner->todayReasons($this->customer->fresh())[0]['line']);

        CustomerPause::create(['customer_id' => $this->customer->id, 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-12']);
        $this->assertSame('আজ বিরতিতে আছেন', $planner->todayReasons($this->customer->fresh())[0]['line']);
        CustomerPause::query()->delete();

        $this->customer->update(['weekdays' => [0]]);
        $this->assertSame('আজ বুকিংয়ের দিন না', $planner->todayReasons($this->customer->fresh())[0]['line']);
    }

    public function test_admin_sees_today_and_can_book_now(): void
    {
        $this->pair();
        // The customer had lunch switched off; the cook posted; then lunch was added back.
        $this->customer->update(['meal_slots' => ['dinner']]);
        $this->livePost();
        $this->customer->update(['meal_slots' => ['lunch']]);

        $this->actingAs($this->admin);
        Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
            ->assertSee('পোস্ট এসেছে, এখনো বক্স হয়নি — এখন বুক করা যায়')
            ->callAction('bookNow')
            ->assertHasNoActionErrors();
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)->count());
    }

    public function test_customer_changing_the_booking_gets_a_box_from_todays_post(): void
    {
        $this->pair();
        $this->customer->update(['meal_slots' => ['dinner']]);
        $this->livePost();

        $this->actingAs($this->customer->user, 'sanctum')->putJson('/api/v1/regular', [
            'name' => 'কল্যাণ', 'area_id' => $this->customer->area_id, 'address' => $this->customer->address,
            'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ])->assertOk()->assertJsonPath('data.today.0.status', 'booked')->assertJsonCount(0, 'data.today_missing');
    }

    public function test_customer_follows_the_box_and_is_told_on_pickup_and_delivery(): void
    {
        Queue::fake();
        $this->livePost();
        $this->pair();
        $order = Order::sole();

        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertJsonPath('data.today.0.picked_at', null);

        app(Delivery::class)->pickup($order, $this->shahidar, null);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->customer->user_id && str_contains($j->title, 'পথে'));

        app(Delivery::class)->deliverByShahidar($order->fresh(), $this->shahidar, null);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->customer->user_id && $j->title === 'খাবার কেমন লাগল?');

        $this->getJson('/api/v1/regular')->assertJsonPath('data.today.0.status', 'delivered')
            ->assertJsonPath('data.today.0.delivered_at', fn ($v) => $v !== null);
    }
}
