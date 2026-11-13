<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
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
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JpegWithExif;
use Tests\Support\PairsLikeBeforeF55;
use Tests\TestCase;

class DailyOrdersTest extends TestCase
{
    use PairsLikeBeforeF55;
    use RefreshDatabase;

    private Kitchen $kitchen;

    private Area $area;

    private User $shahidar;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
        $this->seed(TagSeeder::class);
        // Sunday 8 Nov 2026, 11:30 in Dhaka.
        $this->travelTo(Carbon::parse('2026-11-08 11:30', 'Asia/Dhaka'));
        $this->area = Area::factory()->create(['delivery_fee_paisa' => 2000]);
        $this->kitchen = Kitchen::factory()->approved()->at(23.8069, 90.3654)->create(['area_id' => $this->area->id]);
        $this->admin = User::factory()->admin()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
    }

    private function regular(array $attrs = [], ?string $name = 'নাসির উদ্দিন'): Customer
    {
        $customer = Customer::factory()->create([
            'area_id' => $this->area->id,
            'user_id' => User::factory()->create(['name' => $name])->id,
            ...$attrs,
        ]);
        $this->pairAllowingShared($customer, $this->kitchen, $this->shahidar, $this->admin);

        return $customer->fresh();
    }

    private function publish(int $plates = 5, string $slot = 'lunch')
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, JpegWithExif::make(800, 600, 1));

        return $this->actingAs($this->kitchen->user, 'sanctum')->post('/api/v1/posts', [
            'images' => [new UploadedFile($path, 'bhat.jpg', 'image/jpeg', null, true)],
            'title' => 'ভাত, মুরগির ঝোল',
            'price_taka' => 150,
            'plates_total' => $plates,
            'meal_slot' => $slot,
            'available_hours' => 3,
        ], ['Accept' => 'application/json']);
    }

    public function test_a_post_books_paired_customers_first(): void
    {
        Queue::fake();
        $a = $this->regular(['plates' => 2]);
        $b = $this->regular([], 'রিনা');

        $this->publish(5)->assertCreated();

        $post = Post::firstOrFail();
        $this->assertSame(2, $post->plates_left);
        $this->assertSame(PostStatus::Live, $post->status);
        $orders = Order::orderBy('seq')->get();
        $this->assertCount(2, $orders);
        $first = $orders->firstWhere('customer_id', $a->id);
        $this->assertSame(OrderStatus::Booked, $first->status);
        $this->assertSame(30000, $first->food_paisa);
        $this->assertSame(2000, $first->delivery_fee_paisa);
        $this->assertSame($this->shahidar->id, $first->shahidar_id);
        $this->assertSame('০৮-১', $orders->first()->code());
        $this->assertSame('০৮-২', $orders->last()->code());
        Queue::assertPushed(SendOrderSms::class, 2);
        Queue::assertPushed(SendOrderSms::class, fn ($job) => $job->purpose === OtpPurpose::OrderBooked && strlen($job->code) === 4);
    }

    public function test_sms_text_has_box_total_and_delivery_code(): void
    {
        Log::spy();
        $this->regular(['plates' => 2]);
        $this->publish(5)->assertCreated();

        Log::shouldHaveReceived('info')->withArgs(fn ($msg) => str_contains($msg, 'বক্স ০৮-১')
            && str_contains($msg, '৳৩২০') && str_contains($msg, '২ প্লেট') && preg_match('/বলবেন: \d{4}/u', $msg));
    }

    public function test_too_few_plates_is_refused_and_nothing_is_saved(): void
    {
        $this->regular(['plates' => 3]);

        $this->publish(2)->assertUnprocessable()->assertJsonPath('errors.plates_total.0', 'আজ নিয়মিত গ্রাহকদের বুকিং ৩ প্লেট। অন্তত এতগুলো প্লেট দিন।');
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Order::count());
    }

    public function test_only_matching_meal_weekday_and_unpaused_customers_are_booked(): void
    {
        $dinner = $this->regular(['meal_slots' => ['dinner']]);
        $weekdays = $this->regular(['weekdays' => [1, 2, 3, 4, 5]]); // not Sunday
        $paused = $this->regular();
        app(BookingPlanner::class)->pause($paused, '2026-11-08', '2026-11-09');
        $stopped = $this->regular();
        PairCustomer::stop($stopped, $this->admin, 'বন্ধ');
        $yes = $this->regular();

        $this->publish(5)->assertCreated();

        $this->assertSame([$yes->id], Order::pluck('customer_id')->all());
    }

    public function test_all_plates_booked_hides_the_post_from_the_feed(): void
    {
        $this->regular(['plates' => 3]);
        $this->publish(3)->assertCreated();

        $this->assertSame(PostStatus::SoldOut, Post::first()->status);
        $this->getJson('/api/v1/feed?lat=23.8069&lng=90.3654')->assertJsonCount(0, 'data');
    }

    public function test_skip_returns_plates_to_the_feed(): void
    {
        $customer = $this->regular(['plates' => 3]);
        $this->publish(3)->assertCreated();
        $order = Order::firstOrFail();

        $this->actingAs($customer->user, 'sanctum')->postJson("/api/v1/regular/orders/{$order->id}/skip")
            ->assertOk()->assertJsonPath('data.today.0.status', 'cancelled');

        $post = Post::first();
        $this->assertSame(3, $post->plates_left);
        $this->assertSame(PostStatus::Live, $post->status);
        $this->assertSame('customer', $order->fresh()->cancelled_by);

        // Someone else's order is not theirs to skip.
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/v1/regular/orders/{$order->id}/skip")->assertNotFound();
    }

    public function test_cannot_skip_after_pickup(): void
    {
        $customer = $this->regular();
        $this->publish(3)->assertCreated();
        Order::query()->update(['status' => OrderStatus::PickedUp]);

        $this->actingAs($customer->user, 'sanctum')->postJson('/api/v1/regular/orders/'.Order::first()->id.'/skip')
            ->assertUnprocessable();
    }

    public function test_pausing_today_cancels_todays_box(): void
    {
        $customer = $this->regular(['plates' => 2]);
        $this->publish(4)->assertCreated();

        $this->actingAs($customer->user, 'sanctum')->postJson('/api/v1/regular/pauses', ['starts_on' => '2026-11-08', 'ends_on' => '2026-11-10'])
            ->assertOk()->assertJsonPath('data.pauses.0.ends_on', '2026-11-10');

        $this->assertSame(OrderStatus::Cancelled, Order::first()->status);
        $this->assertSame(4, Post::first()->plates_left);

        $this->actingAs($customer->user, 'sanctum')->postJson('/api/v1/regular/pauses', ['starts_on' => '2026-11-01', 'ends_on' => '2026-11-02'])
            ->assertUnprocessable();
    }

    public function test_cook_off_tells_customers_and_is_not_rebooked(): void
    {
        Queue::fake();
        $this->regular();
        $this->regular([], 'রিনা');

        $this->actingAs($this->kitchen->user, 'sanctum')->postJson('/api/v1/my/bookings/off', ['meal_slot' => 'lunch'])
            ->assertOk()->assertJsonPath('message', '২ জন গ্রাহককে জানানো হচ্ছে। শাহিদার বিকল্প দেখবেন।');

        $this->assertSame(2, Order::where('status', OrderStatus::Cancelled)->where('cancelled_by', 'cook')->count());
        Queue::assertPushed(SendOrderSms::class, fn ($job) => $job->purpose === OtpPurpose::CookOff);

        // Posting later anyway does not double-book.
        $this->publish(2)->assertCreated();
        $this->assertSame(2, Order::count());
        $this->assertSame(2, Post::first()->plates_left);
    }

    public function test_a_second_post_for_the_same_meal_does_not_double_book(): void
    {
        $this->regular();
        $this->publish(3)->assertCreated();
        $this->publish(3)->assertCreated();

        $this->assertSame(1, Order::count());
    }

    public function test_cook_sees_counts_not_names_and_cannot_sell_booked_plates(): void
    {
        $this->regular(['plates' => 2]);
        $waiting = $this->regular(['meal_slots' => ['dinner']], 'রিনা আক্তার');

        $this->publish(5)->assertCreated();

        $json = $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/bookings')->assertOk()->json('data');
        $this->assertSame(2, $json['regular_customers']);
        $lunch = collect($json['slots'])->firstWhere('meal_slot', 'lunch');
        // F44: the cook never learns who her regular customers are.
        $this->assertSame('নিয়মিত গ্রাহক', $lunch['orders'][0]['name']);
        $dinner = collect($json['slots'])->firstWhere('meal_slot', 'dinner');
        $this->assertSame(1, $dinner['waiting_people']);
        $this->assertSame(1, $dinner['waiting_plates']);
        $this->assertStringNotContainsString('রিনা', json_encode($json, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString($waiting->user->phone, json_encode($json));
        $this->assertStringNotContainsString($waiting->address, json_encode($json));

        $post = Post::first();
        $this->actingAs($this->kitchen->user, 'sanctum')->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 4])
            ->assertUnprocessable();
        $this->actingAs($this->kitchen->user, 'sanctum')->patchJson("/api/v1/my/posts/{$post->id}", ['plates_left' => 3])->assertOk();
    }

    public function test_regular_page_lists_todays_box(): void
    {
        $customer = $this->regular();
        $this->publish(3)->assertCreated();

        $this->actingAs($customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertJsonPath('data.today.0.code', '০৮-১')
            ->assertJsonPath('data.today.0.total_paisa', 17000)
            ->assertJsonPath('data.today.0.can_skip', true)
            ->assertJsonMissingPath('data.today.0.delivery_code_hash');
    }

    public function test_planner_uses_dhaka_date_late_at_night(): void
    {
        // 00:30 Monday in Dhaka is still Sunday in UTC.
        $this->travelTo(Carbon::parse('2026-11-09 00:30', 'Asia/Dhaka'));
        $this->assertSame('2026-11-09', BookingPlanner::today());
        $mondayOnly = $this->regular(['weekdays' => [1]]);

        $this->assertCount(1, app(BookingPlanner::class)->expected($this->kitchen, MealSlot::Lunch));
        $this->assertSame(CustomerStatus::Active, $mondayOnly->status);
    }
}
