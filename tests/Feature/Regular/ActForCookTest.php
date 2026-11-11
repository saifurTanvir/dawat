<?php

namespace Tests\Feature\Regular;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenAction;
use App\Models\KitchenMenu;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F46: the admin or a shahidar does a cook's work for her; she is told and it is logged. */
class ActForCookTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $customer;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 11:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Active, 'meal_slots' => ['lunch'], 'plates' => 2]);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
        $this->post = Post::factory()->create([
            'kitchen_id' => $this->kitchen->id, 'meal_slot' => MealSlot::Lunch, 'status' => PostStatus::Live, 'title' => 'ভাত, ডাল',
            'plates_total' => 6, 'plates_left' => 4, 'price_paisa' => 12000, 'published_at' => now()->subHour(), 'available_until' => now()->addHours(2),
        ]);
        Order::create([
            'service_date' => '2026-11-11', 'seq' => 1, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $this->post->id,
            'customer_id' => $this->customer->id, 'shahidar_id' => $this->shahidar->id, 'plates' => 2, 'food_paisa' => 24000, 'delivery_fee_paisa' => 0,
            'status' => OrderStatus::Booked, 'title' => 'ভাত, ডাল',
        ]);
    }

    public function test_shahidar_sees_the_cooks_day_and_marks_ready(): void
    {
        Queue::fake();
        $this->actingAs($this->shahidar, 'sanctum')->getJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}")
            ->assertOk()
            ->assertJsonPath('data.slots.0.can_ready', true)
            ->assertJsonPath('data.slots.0.posts.0.max_left', 4)
            ->assertJsonMissingPath('data.kitchen.location');

        $this->postJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}/ready", ['meal_slot' => 'lunch', 'note' => 'দারোয়ানের কাছে'])->assertOk();

        $this->assertNotNull(Order::sole()->ready_at);
        $log = KitchenAction::sole();
        $this->assertSame('shahidar', $log->role);
        $this->assertStringContainsString('রেডি', $log->details);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->kitchen->user_id && str_contains($j->title, 'শাহিদার করিম'));

        // The cook sees it in her app, without customers' names.
        $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/bookings')
            ->assertJsonPath('data.acted_for.0.who', 'শাহিদার করিম');
    }

    public function test_shahidar_off_needs_a_reason_and_plates_name_soldout_menu_work(): void
    {
        $base = "/api/v1/shahidar/kitchens/{$this->kitchen->id}";
        $this->actingAs($this->shahidar, 'sanctum')->postJson("{$base}/off", ['meal_slot' => 'dinner'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("{$base}/off", ['meal_slot' => 'dinner', 'reason' => 'গৃহিণী অসুস্থ'])->assertOk();

        $this->patchJson("/api/v1/shahidar/posts/{$this->post->id}", ['plates_left' => 5])->assertUnprocessable(); // 2 are booked
        $this->patchJson("/api/v1/shahidar/posts/{$this->post->id}", ['plates_left' => 3, 'title' => 'ভাত, মসুর ডাল'])->assertOk();
        $this->assertSame(3, $this->post->fresh()->plates_left);
        $this->assertSame('ভাত, মসুর ডাল', Order::sole()->title);
        $this->patchJson("/api/v1/shahidar/posts/{$this->post->id}", ['price_taka' => 50])->assertOk();
        $this->assertSame(12000, $this->post->fresh()->price_paisa); // price is not theirs to change

        $this->postJson("/api/v1/shahidar/posts/{$this->post->id}/sold-out")->assertOk();
        $this->assertSame(PostStatus::SoldOut, $this->post->fresh()->status);

        $this->putJson("{$base}/menus/dinner", ['title' => 'রুটি', 'price_taka' => 80, 'plates_total' => 4, 'available_hours' => 2, 'post_at' => '17:00', 'weekdays' => [0, 1]])->assertOk();
        $this->assertSame(1, KitchenMenu::count());
        $this->assertSame(5, KitchenAction::count());
    }

    public function test_shahidar_can_only_act_for_their_cooks(): void
    {
        $other = Kitchen::factory()->approved()->create();
        $this->actingAs($this->shahidar, 'sanctum')->getJson("/api/v1/shahidar/kitchens/{$other->id}")->assertNotFound();

        $buyer = User::factory()->create();
        $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}")->assertForbidden();
    }

    public function test_one_off_orders_can_be_declined_or_handed_for_the_cook(): void
    {
        $buyer = User::factory()->create();
        $make = fn (int $seq, string $receive) => Order::create([
            'service_date' => '2026-11-11', 'seq' => $seq, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $this->post->id,
            'buyer_id' => $buyer->id, 'kind' => 'oneoff', 'receive' => $receive, 'plates' => 1, 'food_paisa' => 12000, 'delivery_fee_paisa' => 0, 'status' => OrderStatus::Booked,
        ]);
        $late = $make(2, 'delivery');
        $late->forceFill(['created_at' => now()->subHour()])->save();
        $self = $make(3, 'self');

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/kitchen-orders/{$late->id}/decline")->assertOk();
        $this->assertSame(OrderStatus::Cancelled, $late->fresh()->status);
        $this->postJson("/api/v1/shahidar/kitchen-orders/{$self->id}/handed")->assertOk();
        $this->assertSame(OrderStatus::Delivered, $self->fresh()->status);
    }

    public function test_admin_acts_from_the_panel_and_the_price_is_locked_once_booked(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'রাশেদ']));

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('readyFor', ['meal_slot' => 'lunch', 'note' => null])->assertHasNoActionErrors();
        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->callAction('contactHoursFor', ['from' => '10:00', 'to' => '18:00', 'prefer' => 'whatsapp'])->assertHasNoActionErrors();
        $this->assertSame('whatsapp', $this->kitchen->user->fresh()->contact_prefer);

        Livewire::test(ViewPost::class, ['record' => $this->post->id])
            ->callAction('platesFor', ['plates_left' => 2])->assertHasNoActionErrors();
        $this->assertSame(2, $this->post->fresh()->plates_left);

        Livewire::test(ViewPost::class, ['record' => $this->post->id])
            ->callAction('editFor', ['title' => 'খিচুড়ি', 'note' => null, 'price_taka' => 200]);
        $this->assertSame(12000, $this->post->fresh()->price_paisa);
        $this->assertSame('খিচুড়ি', $this->post->fresh()->title);

        // A post with no boxes: the price can change.
        $free = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'price_paisa' => 10000]);
        Livewire::test(ViewPost::class, ['record' => $free->id])
            ->callAction('editFor', ['title' => 'পোলাও', 'note' => null, 'price_taka' => 150])->assertHasNoActionErrors();
        $this->assertSame(15000, $free->fresh()->price_paisa);

        $this->get("/admin/kitchens/{$this->kitchen->id}")->assertSee('গৃহিণীর হয়ে যা করা হয়েছে')->assertSee('দাওয়াত টিম (রাশেদ)');
        $this->assertSame('admin', KitchenAction::first()->role);
    }

    public function test_admin_handles_one_off_orders_from_the_orders_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $buyer = User::factory()->create();
        $o = Order::create([
            'service_date' => '2026-11-11', 'seq' => 9, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $this->post->id,
            'buyer_id' => $buyer->id, 'kind' => 'oneoff', 'receive' => 'self', 'plates' => 1, 'food_paisa' => 12000, 'delivery_fee_paisa' => 0, 'status' => OrderStatus::Booked,
        ]);

        Livewire::test(ManageOrders::class)->callTableAction('handedFor', $o)->assertHasNoTableActionErrors();
        $this->assertSame(OrderStatus::Delivered, $o->fresh()->status);
    }
}
