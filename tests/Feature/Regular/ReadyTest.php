<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\MealSlot;
use App\Jobs\SendWebPush;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** F38: the cook says "ready", the shahidar sees it and gets a notification. */
class ReadyTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false, 'services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
        $this->travelTo(Carbon::parse('2026-11-08 12:00', 'Asia/Dhaka'));
        $area = Area::factory()->create();
        $this->kitchen = Kitchen::factory()->approved()->create(['area_id' => $area->id, 'display_name' => 'রহিমা খালার রান্নাঘর']);
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($area);
        $customer = Customer::factory()->create(['area_id' => $area->id]);
        app(PairCustomer::class)($customer, $this->kitchen, $this->shahidar, User::factory()->admin()->create());
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 3, 'plates_left' => 3]);
        app(BookingPlanner::class)->bookForPost($post);
    }

    public function test_cook_marks_lunch_ready_and_the_shahidar_is_told(): void
    {
        Bus::fake([SendWebPush::class]);
        $cook = $this->kitchen->user;

        $this->actingAs($cook, 'sanctum')->getJson('/api/v1/my/bookings')
            ->assertJsonPath('data.slots.0.can_ready', true)
            ->assertJsonPath('data.slots.0.ready_at', null);

        $this->actingAs($cook, 'sanctum')
            ->postJson('/api/v1/my/bookings/ready', ['meal_slot' => 'lunch', 'note' => '২টা পর্যন্ত বাসায়, তারপর দারোয়ানের কাছে'])
            ->assertOk()
            ->assertJsonPath('message', 'শাহিদারকে জানানো হয়েছে (১টা বক্স)।');

        $order = Order::firstOrFail();
        $this->assertNotNull($order->ready_at);
        Bus::assertDispatched(SendWebPush::class, fn ($job) => $job->userId === $this->shahidar->id
            && $job->title === 'রহিমা খালার রান্নাঘর: দুপুরের খাবার রেডি' && str_contains($job->body, 'দারোয়ানের কাছে'));

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')
            ->assertJsonPath('data.orders.0.ready_note', '২টা পর্যন্ত বাসায়, তারপর দারোয়ানের কাছে')
            ->assertJsonPath('data.orders.0.ready_at', $order->ready_at->toIso8601String());

        $this->actingAs($cook, 'sanctum')->getJson('/api/v1/my/bookings')
            ->assertJsonPath('data.slots.0.ready_note', '২টা পর্যন্ত বাসায়, তারপর দারোয়ানের কাছে');
    }

    public function test_nothing_to_collect_is_refused(): void
    {
        $this->actingAs($this->kitchen->user, 'sanctum')
            ->postJson('/api/v1/my/bookings/ready', ['meal_slot' => 'dinner'])
            ->assertUnprocessable();
    }

    public function test_only_cooks_can_say_ready(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/my/bookings/ready', ['meal_slot' => 'lunch'])
            ->assertForbidden();
    }
}
