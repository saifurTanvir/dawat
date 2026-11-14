<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Widgets\MealRequests;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Models\MealChoice;
use App\Models\MenuNotice;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Regular\TodayMenu;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F61: the cook says today's dish; the regular customer can say no; the shahidar sorts it out. */
class TodayMenuTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 09:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        Queue::fake();
        $this->admin = User::factory()->admin()->create();
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create([
            'area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Waiting,
            'meal_slots' => ['lunch', 'dinner'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ]);
        app(PairCustomer::class)($this->customer, $this->kitchen, $this->shahidar, $this->admin);
        $this->customer->refresh();
    }

    private function livePost(MealSlot $slot = MealSlot::Lunch): Post
    {
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'meal_slot' => $slot, 'status' => PostStatus::Live, 'plates_total' => 4, 'plates_left' => 4]);
        app(BookingPlanner::class)->bookForPost($post);

        return $post;
    }

    private function choose(string $choice, string $slot = 'lunch', ?string $wish = null)
    {
        return $this->actingAs($this->customer->user, 'sanctum')->postJson('/api/v1/regular/choices', ['meal_slot' => $slot, 'choice' => $choice, 'wish' => $wish]);
    }

    public function test_cook_announces_and_the_customer_hears_and_sees_it(): void
    {
        $this->actingAs($this->kitchen->user, 'sanctum')->putJson('/api/v1/my/menu-notices/lunch', ['title' => 'খিচুড়ি, ডিম ভুনা'])
            ->assertOk()->assertJsonPath('data.1.title', 'খিচুড়ি, ডিম ভুনা');

        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $this->customer->user_id);

        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertOk()
            ->assertJsonPath('data.today_menu.0.slot', 'lunch')
            ->assertJsonPath('data.today_menu.0.title', 'খিচুড়ি, ডিম ভুনা')
            ->assertJsonPath('data.today_menu.0.choice', 'eat')
            ->assertJsonPath('data.today_menu.1.title', null);
    }

    public function test_skip_before_the_post_means_no_box_and_the_cook_is_told_nothing(): void
    {
        MenuNotice::create(['kitchen_id' => $this->kitchen->id, 'service_date' => '2026-11-15', 'meal_slot' => 'lunch', 'title' => 'খিচুড়ি', 'source' => 'cook']);
        $this->choose('skip')->assertOk()->assertJsonPath('data.today_menu.0.choice', 'skip');
        Queue::assertNotPushed(SendWebPush::class, fn ($job) => $job->userId === $this->kitchen->user_id);

        $this->livePost();
        $this->assertSame(0, Order::count());
        $this->assertSame(0, app(BookingPlanner::class)->bookedPlates($this->kitchen, MealSlot::Lunch));

        // The cook's own screen has no word about it.
        $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/menu-notices')->assertOk()->assertDontSee('skip');

        // Changed their mind before booking: back on, booked from the live post.
        $this->choose('eat')->assertOk();
        $this->assertSame(1, Order::where('status', OrderStatus::Booked)->count());
    }

    public function test_change_cancels_the_box_and_alerts_shahidar_and_admins_who_mark_it_handled(): void
    {
        $this->livePost();
        $order = Order::sole();

        $this->choose('change', 'lunch', '')->assertUnprocessable()->assertJsonValidationErrors(['wish']);
        $this->choose('change', 'lunch', 'মাছ ছাড়া কিছু')->assertOk()
            ->assertJsonPath('data.today_menu.0.choice', 'change')
            ->assertJsonPath('data.today_menu.0.can_undo', false);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $choice = MealChoice::sole();
        $this->assertSame($order->title, $choice->menu_title);
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $this->shahidar->id);
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $this->admin->id);
        Queue::assertNotPushed(SendWebPush::class, fn ($job) => $job->userId === $this->kitchen->user_id && str_contains($job->title, 'খাবেন না'));

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')
            ->assertOk()
            ->assertJsonPath('data.meal_requests.0.wish', 'মাছ ছাড়া কিছু')
            ->assertJsonPath('data.meal_requests.0.phone', $this->customer->user->phone)
            ->assertJsonPath('data.meal_requests.0.handled', false);

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/meal-choices/{$choice->id}/handled", ['note' => 'সালমা আপার মাছ-ভাত'])->assertOk();
        $this->assertSame('handled', $choice->fresh()->status);
        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertJsonPath('data.today_menu.0.handled', true)
            ->assertJsonPath('data.today_menu.0.handled_note', 'সালমা আপার মাছ-ভাত');

        // Another shahidar cannot touch it.
        $other = User::factory()->create();
        $other->assignRole(Roles::SHAHIDAR);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/shahidar/meal-choices/{$choice->id}/handled")->assertNotFound();
    }

    public function test_too_late_once_the_box_has_left(): void
    {
        $this->livePost();
        Order::sole()->forceFill(['status' => OrderStatus::PickedUp, 'picked_at' => now()])->save();

        $this->choose('skip')->assertUnprocessable();
    }

    public function test_daily_menu_announces_itself_at_8_and_only_once(): void
    {
        KitchenMenu::create([
            'kitchen_id' => $this->kitchen->id, 'meal_slot' => 'lunch', 'title' => 'ভাত, ডাল, মাছ', 'price_paisa' => 15000,
            'plates_total' => 4, 'post_at' => '10:00', 'available_hours' => 3, 'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'active' => true,
        ]);

        $this->assertSame(0, app(TodayMenu::class)->runDailyMenus(Carbon::parse('2026-11-15 07:30', 'Asia/Dhaka')));
        $this->assertSame(1, app(TodayMenu::class)->runDailyMenus(Carbon::parse('2026-11-15 08:05', 'Asia/Dhaka')));
        $this->assertSame(0, app(TodayMenu::class)->runDailyMenus(Carbon::parse('2026-11-15 08:10', 'Asia/Dhaka')));
        $this->assertSame('daily_menu', MenuNotice::sole()->source);
    }

    public function test_shahidar_and_admin_can_announce_for_the_cook_and_admin_sees_requests(): void
    {
        $this->actingAs($this->shahidar, 'sanctum')->putJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/menu-notices/dinner", ['title' => 'রুটি, সবজি'])->assertOk();
        $this->assertSame('shahidar', MenuNotice::where('meal_slot', 'dinner')->value('source'));

        $this->actingAs($this->admin);
        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('menuNoticeFor', ['meal_slot' => 'lunch', 'title' => 'খিচুড়ি'])
            ->assertHasNoActionErrors();
        $this->assertSame('admin', MenuNotice::where('meal_slot', 'lunch')->value('source'));

        $this->choose('change', 'lunch', 'শুধু ডাল-ভাত')->assertOk();
        $this->actingAs($this->admin);
        Livewire::test(MealRequests::class)
            ->assertCanSeeTableRecords(MealChoice::all())
            ->callTableAction('handled', MealChoice::sole(), ['note' => 'ফোনে কথা হয়েছে'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('handled', MealChoice::sole()->status);
        $this->assertSame($this->admin->id, MealChoice::sole()->handled_by);
    }
}
