<?php

namespace Tests\Feature\Push;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Otp\OtpPurpose;
use App\Services\Push\OrderPushes;
use App\Services\Push\Push;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** F37: free phone notifications. */
class PushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webpush.public_key' => 'BGbpgNlZUqTsMne-XKXyg_M3O9pgrTKyA3lTYn8rabL4WUJgLNEMcGTF00HW7G-Oqby2e_9q07mKVCtiD4kEZ_A',
            'services.webpush.private_key' => 'f5KXuj_tfWXuq9XeMVinicqtPvAXfwBYKwJqWan2h6o']);
    }

    private function sub(): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BKey'.str_repeat('x', 80), 'auth' => 'authsecret123']];
    }

    public function test_a_signed_in_person_turns_notifications_on_and_off(): void
    {
        $this->getJson('/api/v1/push/key')->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('key', config('services.webpush.public_key'));

        $user = User::factory()->create();
        $this->postJson('/api/v1/push/subscribe', $this->sub())->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/push/subscribe', $this->sub())->assertOk();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/push/subscribe', $this->sub())->assertOk();
        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/push/subscribe', ['endpoint' => $this->sub()['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_insecure_endpoints_are_refused(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/push/subscribe', [...$this->sub(), 'endpoint' => 'http://evil.example/x'])
            ->assertUnprocessable();
    }

    public function test_a_booked_box_notifies_the_customer_and_the_shahidar(): void
    {
        Bus::fake([SendWebPush::class]);
        $customer = Customer::factory()->create(['status' => 'active']);
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $kitchen = Kitchen::factory()->approved()->create();
        $pairing = Pairing::create(['customer_id' => $customer->id, 'kitchen_id' => $kitchen->id, 'shahidar_id' => $shahidar->id, 'started_at' => now()]);
        $order = Order::create([
            'service_date' => today(), 'seq' => 1, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $kitchen->id,
            'customer_id' => $customer->id, 'shahidar_id' => $shahidar->id, 'pairing_id' => $pairing->id,
            'plates' => 1, 'food_paisa' => 15000, 'status' => OrderStatus::Booked, 'title' => 'ভাত, ডাল',
        ]);

        OrderPushes::send($order, OtpPurpose::OrderBooked, '4821', 'recipient');

        Bus::assertDispatched(SendWebPush::class, fn ($job) => $job->userId === $customer->user_id && str_contains($job->body, '4821') && $job->url === '/regular');
        Bus::assertDispatched(SendWebPush::class, fn ($job) => $job->userId === $shahidar->id && $job->url === '/shahidar' && ! str_contains($job->body, '4821'));
    }

    public function test_nothing_is_sent_without_keys(): void
    {
        Bus::fake([SendWebPush::class]);
        config(['services.webpush.private_key' => null]);
        Push::toUser(User::factory()->create(), 'x', 'y');
        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_keys_are_written_once_to_the_env_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "APP_NAME=Dawat\n");

        $this->artisan('dawat:webpush-keys', ['--env-file' => $file])->assertSuccessful();
        $first = file_get_contents($file);
        $this->assertMatchesRegularExpression('/^WEBPUSH_PUBLIC_KEY=B[\w-]{80,}$/m', $first);
        $this->assertStringContainsString('APP_NAME=Dawat', $first);

        $this->artisan('dawat:webpush-keys', ['--env-file' => $file])->assertSuccessful();
        $this->assertSame($first, file_get_contents($file));
        unlink($file);
    }
}
