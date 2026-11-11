<?php

namespace Tests\Feature\Regular;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Kitchens\RelationManagers\ReviewsRelationManager;
use App\Jobs\SendWebPush;
use App\Models\BoxReview;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** F47: box reviews, the cook's sales page and the customer's history. */
class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $customer;

    private User $buyer;

    private Order $regular;

    private Order $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 14:00', 'Asia/Dhaka'));
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y', 'dawat.orders.commission_percent' => 10]);
        $this->kitchen = Kitchen::factory()->approved()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->kitchen->area_id);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Active]);
        $this->customer->user->update(['name' => 'নাসির উদ্দিন']);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()]);
        $this->buyer = User::factory()->create(['name' => 'তানিয়া']);
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'title' => 'ভাত, মাছ']);

        $this->regular = Order::create([
            'service_date' => '2026-11-12', 'seq' => 1, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $post->id,
            'customer_id' => $this->customer->id, 'shahidar_id' => $this->shahidar->id, 'plates' => 1, 'food_paisa' => 15000, 'delivery_fee_paisa' => 2000,
            'status' => OrderStatus::PickedUp, 'title' => 'ভাত, মাছ',
        ]);
        $this->feed = Order::create([
            'service_date' => '2026-11-12', 'seq' => 2, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $post->id,
            'buyer_id' => $this->buyer->id, 'kind' => 'oneoff', 'receive' => 'delivery', 'shahidar_id' => $this->shahidar->id,
            'plates' => 2, 'food_paisa' => 30000, 'delivery_fee_paisa' => 0, 'status' => OrderStatus::PickedUp, 'title' => 'ভাত, মাছ',
        ]);
    }

    private function deliverBoth(): void
    {
        app(Delivery::class)->deliverByShahidar($this->regular, $this->shahidar, null);
        app(Delivery::class)->deliverByShahidar($this->feed, $this->shahidar, null);
    }

    public function test_delivery_asks_for_a_review_and_both_kinds_of_customer_can_give_one(): void
    {
        Queue::fake();
        $this->deliverBoth();
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->customer->user_id && $j->title === 'খাবার কেমন লাগল?' && $j->url === '/history');

        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 5, 'body' => 'খুব মজা'])
            ->assertOk()->assertJsonPath('data.review.stars', 5);
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/my/history/{$this->feed->id}/review", ['stars' => 4])->assertOk();

        $k = $this->kitchen->fresh();
        $this->assertSame(2, $k->rating_count);
        $this->assertSame('4.50', (string) $k->rating_avg);

        // Someone else's box: no.
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 1])->assertNotFound();
    }

    public function test_reviews_close_three_days_after_delivery_and_need_delivery(): void
    {
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 5])->assertUnprocessable();

        $this->deliverBoth();
        $this->travel(4)->days();
        $this->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 5])->assertUnprocessable();
        $this->getJson('/api/v1/my/history')->assertJsonPath('data.0.can_review', false);
    }

    public function test_low_stars_alert_the_shahidar_and_admins(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $this->deliverBoth();
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 2, 'body' => 'ঠান্ডা ছিল'])->assertOk();

        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $this->shahidar->id && str_starts_with($j->title, 'কম রেটিং'));
        Queue::assertPushed(SendWebPush::class, fn ($j) => $j->userId === $admin->id && str_contains($j->body, 'ঠান্ডা ছিল'));
    }

    public function test_cook_sees_sales_and_reviews_without_names_and_replies(): void
    {
        $this->deliverBoth();
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 5, 'body' => 'খুব মজা']);
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/my/history/{$this->feed->id}/review", ['stars' => 3]);

        $json = $this->actingAs($this->kitchen->user, 'sanctum')->getJson('/api/v1/my/sales')->assertOk()
            ->assertJsonPath('data.days.0.regular_plates', 1)
            ->assertJsonPath('data.days.0.feed_plates', 2)
            ->assertJsonPath('data.days.0.delivered', 2)
            ->assertJsonPath('data.days.0.earned_paisa', 40500) // (150 + 300) less 10%
            ->assertJsonPath('data.totals.due_paisa', 40500)
            ->json();
        $text = json_encode($json, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('নাসির', $text);
        $this->assertStringNotContainsString('তানিয়া', $text);
        $this->assertContains('একজন নিয়মিত গ্রাহক', array_column($json['data']['reviews'], 'who'));
        $this->assertContains('ফিডের ক্রেতা', array_column($json['data']['reviews'], 'who'));

        $review = BoxReview::where('order_id', $this->regular->id)->sole();
        $this->postJson("/api/v1/my/reviews/{$review->id}/reply", ['reply' => 'ধন্যবাদ!'])->assertOk();

        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/my/history')
            ->assertJsonPath('data.0.review.reply', 'ধন্যবাদ!')
            ->assertJsonPath('data.0.review.reply_label', 'গৃহিণী');
    }

    public function test_history_shows_money_cancellations_and_who_delivered(): void
    {
        $this->deliverBoth();
        Order::create([
            'service_date' => '2026-11-11', 'seq' => 3, 'meal_slot' => MealSlot::Dinner, 'kitchen_id' => $this->kitchen->id,
            'customer_id' => $this->customer->id, 'plates' => 1, 'food_paisa' => 15000, 'delivery_fee_paisa' => 0,
            'status' => OrderStatus::Cancelled, 'cancelled_by' => 'customer', 'cancel_reason' => 'গ্রাহক: আজ না', 'title' => 'খিচুড়ি',
        ]);

        $rows = $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/my/history')->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame('বাকি', $rows[0]['payment']); // no cash marked yet
        app(Settlements::class)->markPaid($this->regular->fresh(), $this->shahidar);
        $this->assertSame('পরিশোধ হয়েছে', $this->getJson('/api/v1/my/history')->json('data.0.payment'));
        $this->assertSame('শাহিদার করিম', $rows[0]['delivered_by']);
        $this->assertTrue($rows[0]['can_review']);
        $this->assertSame('গ্রাহক: আজ না', $rows[1]['reason']);
        $this->assertNull($rows[1]['payment']);
    }

    public function test_only_stars_are_public(): void
    {
        $this->deliverBoth();
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 4, 'body' => 'গোপন কথা']);
        $live = Post::factory()->create(['kitchen_id' => $this->kitchen->id]);

        $text = json_encode($this->getJson("/api/v1/posts/{$live->id}")->assertOk()->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('"rating_count":1', $text);
        $this->assertStringNotContainsString('গোপন কথা', $text);
    }

    public function test_shahidar_and_admin_see_names_reply_and_hide(): void
    {
        $this->deliverBoth();
        $this->actingAs($this->customer->user, 'sanctum')->postJson("/api/v1/my/history/{$this->regular->id}/review", ['stars' => 3, 'body' => 'ঝাল বেশি']);
        $review = BoxReview::sole();

        $this->actingAs($this->shahidar, 'sanctum')->getJson("/api/v1/shahidar/kitchens/{$this->kitchen->id}")
            ->assertJsonPath('data.sales.reviews.0.who', 'নাসির উদ্দিন (একজন নিয়মিত গ্রাহক)');
        $this->postJson("/api/v1/shahidar/reviews/{$review->id}/reply", ['reply' => 'জানিয়ে দিচ্ছি'])->assertOk();
        $this->assertSame('shahidar', $review->fresh()->reply_role);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ReviewsRelationManager::class, ['ownerRecord' => $this->kitchen, 'pageClass' => ViewKitchen::class])
            ->assertSee('নাসির উদ্দিন')->assertSee('ঝাল বেশি')
            ->callTableAction('hideReply', $review);
        $this->assertTrue($review->fresh()->reply_hidden);
        $this->actingAs($this->customer->user, 'sanctum')->getJson('/api/v1/my/history')->assertJsonPath('data.0.review.reply', null);
    }
}
