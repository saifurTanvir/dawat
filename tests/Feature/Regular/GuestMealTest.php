<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Filament\Widgets\GuestMealRequests;
use App\Jobs\SendOrderSms;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\GuestMeal;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F64: a regular customer asks for guest plates; cook, shahidar and admin sort it out. */
class GuestMealTest extends TestCase
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
            'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ]);
        $this->customer->user->update(['name' => 'রহিম সাহেব']);
        app(PairCustomer::class)($this->customer, $this->kitchen, $this->shahidar, $this->admin);
        $this->customer->refresh();
    }

    private function ask(int $plates = 2, string $day = 'today', string $slot = 'lunch')
    {
        return $this->actingAs($this->customer->user, 'sanctum')->postJson('/api/v1/regular/guest-meals', [
            'day' => $day, 'meal_slot' => $slot, 'plates' => $plates, 'note' => 'দুজন মেহমান আসবেন',
        ]);
    }

    private function livePost(int $plates = 6, ?Kitchen $kitchen = null): Post
    {
        $post = Post::factory()->create([
            'kitchen_id' => ($kitchen ?? $this->kitchen)->id, 'meal_slot' => MealSlot::Lunch, 'status' => PostStatus::Live,
            'plates_total' => $plates, 'plates_left' => $plates, 'price_paisa' => 12000, 'published_at' => now(),
        ]);
        app(BookingPlanner::class)->bookForPost($post);

        return $post->fresh();
    }

    private function pushedTo(int $userId): bool
    {
        return Queue::pushed(SendWebPush::class, fn ($job) => $job->userId === $userId)->isNotEmpty();
    }

    public function test_customer_asks_and_cook_and_shahidar_are_told_but_the_cook_never_sees_the_name(): void
    {
        $this->ask()->assertOk()->assertJsonPath('data.0.status', 'asked')->assertJsonPath('data.0.when', 'আজ দুপুর');
        $this->assertTrue($this->pushedTo($this->kitchen->user_id));
        $this->assertTrue($this->pushedTo($this->shahidar->id));

        $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/guest-meals')
            ->assertOk()->assertJsonPath('data.0.plates', 2)->assertJsonPath('data.0.can_reply', true)
            ->assertDontSee('রহিম')->assertDontSee($this->customer->user->phone);

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/guest-meals')
            ->assertOk()->assertJsonPath('data.0.customer', 'রহিম সাহেব');

        // Asking again for the same meal updates the request.
        $this->ask(3)->assertOk();
        $this->assertSame(1, GuestMeal::count());
        $this->assertSame(3, GuestMeal::first()->plates);
    }

    public function test_only_paired_customers_can_ask(): void
    {
        $other = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Waiting]);
        $this->actingAs($other->user, 'sanctum')->postJson('/api/v1/regular/guest-meals', ['day' => 'today', 'meal_slot' => 'lunch', 'plates' => 2])
            ->assertStatus(422);
        $this->ask(11)->assertStatus(422);
    }

    public function test_cook_says_yes_with_a_live_post_and_the_guest_box_is_booked_at_once(): void
    {
        $post = $this->livePost(6); // 1 plate goes to the regular box
        $this->ask(2)->assertOk();
        $guest = GuestMeal::first();

        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/guest-meals/{$guest->id}/reply", ['yes' => true])->assertOk();

        $guest->refresh();
        $this->assertSame('arranged', $guest->status);
        $order = $guest->order;
        $this->assertSame('oneoff', $order->kind);
        $this->assertSame($this->customer->user_id, $order->buyer_id);
        $this->assertSame($this->shahidar->id, $order->shahidar_id);
        $this->assertSame(2, $order->plates);
        $this->assertSame(24000, $order->food_paisa);
        $this->assertSame(0, $order->delivery_fee_paisa); // rides with the regular box
        $this->assertSame(3, $post->fresh()->plates_left);
        Queue::assertPushed(SendOrderSms::class, fn ($job) => $job->orderId === $order->id);

        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/regular')
            ->assertOk()->assertJsonPath('data.guest_meals.0.status', 'arranged')->assertJsonPath('data.guest_meals.0.order_code', $order->code());
    }

    public function test_cook_says_yes_before_posting_and_the_post_books_the_guest_plates(): void
    {
        $this->ask(2)->assertOk();
        $guest = GuestMeal::first();
        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/guest-meals/{$guest->id}/reply", ['yes' => true])->assertOk();
        $this->assertSame('cook_yes', $guest->fresh()->status);

        $this->actingAs($this->kitchen->user, 'sanctum')->post('/api/v1/posts', [
            'images' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
            'title' => 'মুরগি-ভাত', 'price_taka' => 120, 'plates_total' => 5, 'meal_slot' => 'lunch', 'available_hours' => 3,
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $this->assertSame('arranged', $guest->fresh()->status);
        $this->assertSame(2, Order::where('guest_meal_id', $guest->id)->value('plates'));
        $this->assertSame(2, Post::latest('id')->value('plates_left')); // 5 − 1 regular − 2 guest
    }

    public function test_no_answer_in_five_minutes_alerts_the_shahidar_and_admins_once(): void
    {
        $this->ask()->assertOk();
        Queue::fake();
        $this->travel(4)->minutes();
        $this->artisan('guests:follow-up')->assertSuccessful();
        $this->assertFalse($this->pushedTo($this->shahidar->id));

        $this->travel(2)->minutes();
        $this->artisan('guests:follow-up')->assertSuccessful();
        $this->assertTrue($this->pushedTo($this->shahidar->id));
        $this->assertTrue($this->pushedTo($this->admin->id));
        $this->assertNotNull(GuestMeal::first()->followup_at);

        Queue::fake();
        $this->artisan('guests:follow-up')->assertSuccessful();
        $this->assertFalse($this->pushedTo($this->shahidar->id));

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/guest-meals')->assertJsonPath('data.0.late', true);
    }

    public function test_cook_cannot_so_the_shahidar_books_from_another_cooks_post_with_the_area_fee(): void
    {
        $this->kitchen->area->update(['delivery_fee_paisa' => 3000]);
        $this->ask(2)->assertOk();
        $guest = GuestMeal::first();
        $this->actingAs($this->kitchen->user, 'sanctum')->postJson("/api/v1/my/guest-meals/{$guest->id}/reply", ['yes' => false])->assertOk();
        $this->assertSame('cook_no', $guest->fresh()->status);
        $this->assertTrue($this->pushedTo($this->shahidar->id));

        $otherKitchen = Kitchen::factory()->approved()->create(['area_id' => $this->kitchen->area_id]);
        $other = $this->livePost(4, $otherKitchen);

        $this->actingAs($this->shahidar, 'sanctum')->getJson("/api/v1/shahidar/guest-meals/{$guest->id}/posts")
            ->assertOk()->assertJsonPath('data.0.id', $other->id)->assertJsonPath('data.0.own_cook', false);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/guest-meals/{$guest->id}/arrange", ['post_id' => $other->id])->assertOk();

        $order = $guest->fresh()->order;
        $this->assertSame($otherKitchen->id, $order->kitchen_id);
        $this->assertSame(3000, $order->delivery_fee_paisa);
        $this->assertSame(2, $other->fresh()->plates_left);

        // Another shahidar cannot touch it.
        $stranger = User::factory()->create();
        $stranger->assignRole(Roles::SHAHIDAR);
        $this->actingAs($stranger, 'sanctum')->postJson("/api/v1/shahidar/guest-meals/{$guest->id}/close", [])->assertNotFound();
    }

    public function test_shahidar_arranges_another_way_or_declines_and_the_customer_is_told(): void
    {
        $this->ask()->assertOk();
        $guest = GuestMeal::first();
        Queue::fake();
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/guest-meals/{$guest->id}/close", ['note' => 'পাশের বাসার আপা দেবেন'])->assertOk();
        $this->assertSame('arranged', $guest->fresh()->status);
        $this->assertTrue($this->pushedTo($this->customer->user_id));

        $this->ask(1, 'tomorrow')->assertOk();
        $next = GuestMeal::latest('id')->first();
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/guest-meals/{$next->id}/close", ['cancel' => true])->assertOk();
        $this->assertSame('cancelled', $next->fresh()->status);
    }

    public function test_customer_can_cancel_while_open(): void
    {
        $this->ask()->assertOk();
        $guest = GuestMeal::first();
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/regular/guest-meals/{$guest->id}/cancel")->assertOk()
            ->assertJsonPath('data.0.status', 'cancelled');
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/regular/guest-meals/{$guest->id}/cancel")->assertStatus(422);
    }

    public function test_admin_widget_lists_and_books_from_a_post(): void
    {
        $post = $this->livePost(6);
        $this->ask(2)->assertOk();
        $guest = GuestMeal::first();
        $this->actingAs($this->admin);

        Livewire::test(GuestMealRequests::class)
            ->assertSee('রহিম সাহেব')->assertSee('গৃহিণীর উত্তরের অপেক্ষায়')
            ->callTableAction('fromPost', $guest, ['post_id' => $post->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame('arranged', $guest->fresh()->status);
    }
}
