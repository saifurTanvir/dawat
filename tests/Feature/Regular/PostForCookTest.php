<?php

namespace Tests\Feature\Regular;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Services\Posts\PostForCook;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** F43: posts made for a cook by the team, her shahidar or her daily menu. */
class PostForCookTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 11 Nov 2026, 09:00 Dhaka.
        $this->travelTo(Carbon::parse('2026-11-11 09:00', 'Asia/Dhaka'));
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Active, 'meal_slots' => ['lunch'], 'plates' => 2]);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
    }

    private function menu(array $attrs = []): KitchenMenu
    {
        return KitchenMenu::create([
            'kitchen_id' => $this->kitchen->id, 'meal_slot' => 'lunch', 'title' => 'ভাত, ডাল, মুরগি', 'price_paisa' => 12000,
            'plates_total' => 5, 'available_hours' => 3, 'post_at' => '10:00', 'weekdays' => [0, 1, 2, 3, 4, 5, 6], ...$attrs,
        ]);
    }

    public function test_the_daily_menu_posts_itself_at_its_time_and_books_regular_customers(): void
    {
        $this->menu();
        $old = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'title' => 'ভাত, ডাল, মুরগি', 'published_at' => now()->subDay()]);
        PostImage::create(['post_id' => $old->id, 'path' => 'posts/x/a.webp', 'sort_order' => 0, 'width' => 800, 'height' => 600]);

        $this->artisan('posts:daily-menus')->assertSuccessful();
        $this->assertSame(1, Post::count()); // not yet 10:00

        $this->travelTo(Carbon::parse('2026-11-11 10:01', 'Asia/Dhaka'));
        $this->artisan('posts:daily-menus');
        $this->artisan('posts:daily-menus'); // only once

        $post = Post::where('source', 'auto')->sole();
        $this->assertSame('ভাত, ডাল, মুরগি', $post->title);
        $this->assertSame(3, $post->plates_left);
        $this->assertSame('posts/x/a.webp', $post->images()->value('path'));
        $order = Order::where('customer_id', $this->customer->id)->sole();
        $this->assertSame(OrderStatus::Booked, $order->status);
    }

    public function test_day_off_or_her_own_post_stops_the_menu(): void
    {
        $this->menu();
        $this->actingAs($this->kitchen->user, 'sanctum')->postJson('/api/v1/my/bookings/off', ['meal_slot' => 'lunch'])->assertOk();
        $this->travelTo(Carbon::parse('2026-11-11 10:01', 'Asia/Dhaka'));
        $this->artisan('posts:daily-menus');
        $this->assertSame(0, Post::count());

        // Another day she posted herself before the menu time.
        $this->travelTo(Carbon::parse('2026-11-12 09:30', 'Asia/Dhaka'));
        Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'published_at' => now()]);
        $this->travelTo(Carbon::parse('2026-11-12 10:05', 'Asia/Dhaka'));
        $this->artisan('posts:daily-menus');
        $this->assertSame(0, Post::where('source', 'auto')->count());
    }

    public function test_a_menu_is_not_posted_hours_late_or_on_other_days(): void
    {
        $this->menu(['weekdays' => [3]]); // Wednesday
        $this->travelTo(Carbon::parse('2026-11-11 12:30', 'Asia/Dhaka'));
        $this->artisan('posts:daily-menus');
        $this->assertSame(0, Post::count());

        $this->travelTo(Carbon::parse('2026-11-12 10:05', 'Asia/Dhaka')); // Thursday
        $this->artisan('posts:daily-menus');
        $this->assertSame(0, Post::count());
    }

    public function test_too_few_plates_tells_the_cook_and_does_not_retry(): void
    {
        $this->menu(['plates_total' => 1]); // the customer needs 2
        $this->travelTo(Carbon::parse('2026-11-11 10:01', 'Asia/Dhaka'));
        $this->artisan('posts:daily-menus');
        $this->artisan('posts:daily-menus');
        $this->assertSame(0, Post::count());
        $this->assertTrue(PostForCook::offToday($this->kitchen->id, MealSlot::Lunch));
    }

    public function test_cook_manages_menus_from_the_app(): void
    {
        $this->actingAs($this->kitchen->user, 'sanctum')->putJson('/api/v1/my/menus/lunch', [
            'title' => 'খিচুড়ি', 'price_taka' => 100, 'plates_total' => 6, 'available_hours' => 2, 'post_at' => '10:30', 'weekdays' => [0, 3],
        ])->assertOk()
            ->assertJsonPath('data.0.weekdays_label', 'রবি, বুধ')
            ->assertJsonPath('data.0.today', 'আজ সকাল ১০:৩০-এ নিজে পোস্ট হবে');

        $this->putJson('/api/v1/my/menus/lunch', ['title' => 'খিচুড়ি', 'price_taka' => 100, 'plates_total' => 6, 'available_hours' => 2, 'post_at' => '05:00', 'weekdays' => [0]])
            ->assertUnprocessable()->assertJsonValidationErrors('post_at');

        $this->deleteJson('/api/v1/my/menus/lunch')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_shahidar_posts_for_a_cook_in_their_area(): void
    {
        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')
            ->assertJsonPath('data.waiting.0.kitchen_id', $this->kitchen->id)
            ->assertJsonPath('data.waiting.0.meal_slot', 'lunch');

        $this->postJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/posts", [
            'title' => 'ভাত, মাছ', 'price_taka' => 130, 'plates_total' => 4, 'meal_slot' => 'lunch', 'available_hours' => 3,
        ])->assertCreated();

        $post = Post::sole();
        $this->assertSame('shahidar', $post->source);
        $this->assertSame($this->shahidar->id, $post->posted_by);
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)->count());

        $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/posts')
            ->assertJsonPath('data.0.source_label', 'শাহিদার');

        $other = Kitchen::factory()->approved()->create();
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/kitchens/{$other->id}/posts", [
            'title' => 'ভাত', 'price_taka' => 130, 'plates_total' => 4, 'meal_slot' => 'lunch', 'available_hours' => 3,
        ])->assertNotFound();

        $buyer = User::factory()->create();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/posts", [])->assertForbidden();
    }

    public function test_admin_posts_for_a_cook_and_sets_her_menus(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('postFor', ['meal_slot' => 'lunch', 'title' => 'পোলাও, রোস্ট', 'price_taka' => 200, 'plates_total' => 5, 'available_hours' => 3])
            ->assertHasNoActionErrors();
        $this->assertSame('admin', Post::sole()->source);
        $this->assertSame(1, Order::count());

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('menus', ['menus' => [[
                'meal_slot' => 'dinner', 'title' => 'রুটি, সবজি', 'price_taka' => 90, 'plates_total' => 4,
                'post_at' => '17:00', 'available_hours' => 3, 'weekdays' => ['0', '1'], 'active' => true, 'note' => null,
            ]]])
            ->assertHasNoActionErrors();
        $menu = KitchenMenu::sole();
        $this->assertSame('17:00', $menu->postAt());
        $this->assertSame([0, 1], $menu->weekdays);
    }
}
