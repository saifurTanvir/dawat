<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Jobs\SendWebPush;
use App\Models\BotFact;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\MealChoice;
use App\Models\Order;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** F84: a regular customer who does not like today's dish picks another cook's food nearby. */
class PickOtherCookTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-21 10:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->kitchen = Kitchen::factory()->approved()->at(23.7800, 90.4100)->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create([
            'area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Waiting,
            'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        ]);
        app(PairCustomer::class)($this->customer, $this->kitchen, $this->shahidar, $admin);
        $this->customer->refresh();
        $this->customer->location = GeoPoint::make(23.7805, 90.4105);
        $this->customer->save();
    }

    private function makePost(Kitchen $k, string $title, int $taka, array $extra = []): Post
    {
        return Post::factory()->create(['kitchen_id' => $k->id, 'meal_slot' => MealSlot::Lunch, 'status' => PostStatus::Live,
            'title' => $title, 'price_paisa' => $taka * 100, 'plates_total' => 3, 'plates_left' => 3] + $extra);
    }

    public function test_she_sees_other_cooks_food_nearby_and_filters_it(): void
    {
        $own = $this->makePost($this->kitchen, 'লাউ দিয়ে মাছ', 100);
        app(BookingPlanner::class)->bookForPost($own);
        $near = Kitchen::factory()->approved()->at(23.7820, 90.4120)->create(['area_id' => $this->kitchen->area_id, 'display_name' => 'রুমার রান্নাঘর']);
        $near2 = Kitchen::factory()->approved()->at(23.7900, 90.4150)->create(['area_id' => $this->kitchen->area_id]);
        $far = Kitchen::factory()->approved()->at(23.7000, 90.3400)->create();
        $tag = Tag::factory()->create(['name' => 'মুরগি', 'slug' => 'chicken']);
        $this->makePost($near, 'মুরগির ঝোল, ভাত', 120)->tags()->attach($tag);
        $this->makePost($near2, 'খিচুড়ি, ডিম', 80);
        $this->makePost($far, 'বিরিয়ানি', 150);

        $this->actingAs($this->customer->user, 'sanctum');
        $all = $this->getJson('/api/v1/regular/nearby?meal_slot=lunch')->assertOk()->json('data');
        $this->assertSame(['মুরগির ঝোল, ভাত', 'খিচুড়ি, ডিম'], array_column($all, 'title')); // own cook and far one left out, nearest first
        $this->assertArrayNotHasKey('location', $all[0]);
        $this->assertIsInt($all[0]['distance_m']);

        $this->assertSame(['খিচুড়ি, ডিম'], array_column($this->getJson('/api/v1/regular/nearby?meal_slot=lunch&sort=cheap&max_taka=100')->json('data'), 'title'));
        $this->assertSame(['মুরগির ঝোল, ভাত'], array_column($this->getJson('/api/v1/regular/nearby?meal_slot=lunch&tags[]=chicken')->json('data'), 'title'));
        $this->assertSame(['মুরগির ঝোল, ভাত'], array_column($this->getJson('/api/v1/regular/nearby?meal_slot=lunch&q=রুমা')->json('data'), 'title'));
    }

    public function test_picking_cancels_her_cooks_box_and_books_the_other_one_through_her_shahidar(): void
    {
        $own = $this->makePost($this->kitchen, 'লাউ দিয়ে মাছ', 100);
        app(BookingPlanner::class)->bookForPost($own);
        $mine = Order::where('customer_id', $this->customer->id)->firstOrFail();
        $near = Kitchen::factory()->approved()->at(23.7820, 90.4120)->create(['area_id' => $this->kitchen->area_id]);
        $other = $this->makePost($near, 'মুরগির ঝোল, ভাত', 120);

        $this->actingAs($this->customer->user, 'sanctum')
            ->postJson('/api/v1/regular/choices', ['meal_slot' => 'lunch', 'choice' => 'pick', 'post_id' => $other->id])
            ->assertOk()->assertJsonPath('data.today_menu.0.picked.title', 'মুরগির ঝোল, ভাত');

        // Her one box for the meal now comes from the other cook; her cook's plate goes back on the feed.
        $new = $mine->fresh();
        $this->assertSame(OrderStatus::Booked, $new->status);
        $this->assertSame(3, $own->fresh()->plates_left);
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)->count());
        $this->assertSame($near->id, $new->kitchen_id);
        $this->assertSame($this->shahidar->id, $new->shahidar_id);
        $this->assertSame(12000, $new->food_paisa);
        $this->assertSame(2, $other->fresh()->plates_left);
        $choice = MealChoice::firstOrFail();
        $this->assertSame('pick', $choice->choice);
        $this->assertSame($new->id, $choice->order_id);
        // Her shahidar and the other cook hear; her own cook does not.
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->shahidar->id);
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $near->user_id);
        Queue::assertNotPushed(SendWebPush::class, fn ($j) => $j->userId === $this->kitchen->user_id);

        // A far post, or her own cook's, cannot be picked; "খাব" cannot undo a pick.
        $far = $this->makePost(Kitchen::factory()->approved()->at(23.70, 90.34)->create(), 'বিরিয়ানি', 150);
        $this->postJson('/api/v1/regular/choices', ['meal_slot' => 'lunch', 'choice' => 'pick', 'post_id' => $far->id])->assertStatus(422);
        $this->postJson('/api/v1/regular/choices', ['meal_slot' => 'lunch', 'choice' => 'eat'])->assertStatus(422);
    }

    public function test_the_bot_knows_about_it(): void
    {
        $this->assertStringContainsString('এলাকার অন্য খাবার দেখুন', BotFact::promptText());
    }
}
