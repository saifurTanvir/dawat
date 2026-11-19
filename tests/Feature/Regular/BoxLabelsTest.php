<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Jobs\SendWebPush;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Order;
use App\Models\Post;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\BoxLabels;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** F79: named box stickers printed by the shahidar; office boxes get a row and column. */
class BoxLabelsTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $shahidar;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false, 'services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->travelTo(Carbon::parse('2026-11-19 10:00', 'Asia/Dhaka'));
        $this->area = Area::factory()->create(['name' => 'বনানী']);
        $this->admin = User::factory()->admin()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
    }

    private function customer(string $name, Kitchen $kitchen, ?Company $company = null, string $phone = ''): Customer
    {
        $c = Customer::factory()->create(['area_id' => $this->area->id, 'company_id' => $company?->id, 'address' => 'বাড়ি ১২, রোড ৫', 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        $c->user->update(['name' => $name] + ($phone ? ['phone' => $phone] : []));
        app(PairCustomer::class)($c, $kitchen, $this->shahidar, $this->admin);

        return $c;
    }

    private function cookPosts(Kitchen $kitchen): void
    {
        $post = Post::factory()->create(['kitchen_id' => $kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 10, 'plates_left' => 10]);
        app(BookingPlanner::class)->bookForPost($post);
    }

    public function test_print_makes_one_named_sticker_per_box_once_and_the_sheet_shows_them(): void
    {
        $k = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->customer('রহিম উদ্দিন', $k, null, '01711112222');
        $this->cookPosts($k);

        $res = $this->actingAs($this->shahidar, 'sanctum')->postJson('/api/v1/shahidar/stickers/print')->assertOk()
            ->assertJsonPath('data.count', 1);
        $order = Order::firstOrFail();
        $this->assertNotNull($order->sticker);
        $this->assertNotNull($order->sticker->printed_at);
        $this->assertNull($order->sticker->bound_at); // stuck on at pickup

        // Nothing new: nothing printed twice.
        $this->postJson('/api/v1/shahidar/stickers/print')->assertStatus(422);
        $this->getJson('/api/v1/shahidar/stickers/printed')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/shahidar/today')->assertJsonPath('data.orders.0.sticker', $order->sticker->displayCode());

        // The sheet: signed link only.
        $url = $res->json('data.url');
        $this->get($url)->assertOk()->assertSee('রহিম উদ্দিন')->assertSee('01711112222')->assertSee('বাড়ি ১২, রোড ৫')
            ->assertSee('size: 75mm 100mm', false)->assertSee('কল করতে স্ক্যান করুন')->assertSee('দুপুর');
        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_pickup_scan_accepts_the_right_printed_sticker_and_names_a_wrong_one(): void
    {
        $k = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $k2 = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $rahim = $this->customer('রহিম', $k);
        $selim = $this->customer('সেলিম', $k2);
        $this->cookPosts($k);
        $this->cookPosts($k2);
        $this->actingAs($this->shahidar, 'sanctum')->postJson('/api/v1/shahidar/stickers/print')->assertOk();

        $a = Order::where('customer_id', $rahim->id)->firstOrFail();
        $b = Order::where('customer_id', $selim->id)->firstOrFail();

        $this->postJson("/api/v1/shahidar/orders/{$a->id}/pickup", ['sticker' => $b->sticker->code])
            ->assertStatus(422)->assertJsonFragment(['sticker' => ["⚠️ ভুল স্টিকার! এটা সেলিম-এর বক্সের ({$b->code()})। রহিম-এর নামের স্টিকারটা লাগান।"]]);
        Sticker::create(['code' => 'BLNK2222', 'batch' => 'x']);
        $this->postJson("/api/v1/shahidar/orders/{$a->id}/pickup", ['sticker' => 'BLNK2222'])->assertStatus(422);

        $this->postJson("/api/v1/shahidar/orders/{$a->id}/pickup", ['sticker' => $a->sticker->code])->assertOk();
        $this->assertNotNull($a->sticker->fresh()->bound_at);
        $this->assertSame(OrderStatus::PickedUp, $a->fresh()->status);
    }

    public function test_office_boxes_get_rows_and_columns_and_employees_hear_their_spot(): void
    {
        Queue::fake();
        $contact = User::factory()->create();
        $office = Company::create(['name' => 'ABC', 'address' => 'লেভেল ৫', 'area_id' => $this->area->id, 'contact_user_id' => $contact->id, 'drop_note' => 'রিসেপশনে']);
        $k = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $names = ['আনিস', 'বীথি', 'চন্দন', 'দীপা', 'এহসান', 'ফারুক', 'গীতা'];
        foreach ($names as $n) {
            $this->customer($n, $k, $office);
        }
        $this->cookPosts($k);

        $this->actingAs($this->shahidar, 'sanctum')->postJson('/api/v1/shahidar/stickers/print')->assertOk()->assertJsonPath('data.count', 7);
        $spots = Order::with('customer.user')->get()->mapWithKeys(fn (Order $o) => [$o->customer->user->name => BoxLabels::spot($o)]);
        $this->assertSame('সারি ১ · কলাম ১', $spots['আনিস']);
        // Bangla alphabetical: vowels first — আনিস, এহসান, গীতা, চন্দন, দীপা, then ফারুক, বীথি.
        $this->assertSame('সারি ১ · কলাম ২', $spots['এহসান']);
        $this->assertSame('সারি ১ · কলাম ৫', $spots['দীপা']);
        $this->assertSame('সারি ২ · কলাম ১', $spots['ফারুক']);
        $this->assertSame('সারি ২ · কলাম ২', $spots['বীথি']);
        $this->assertSame(7, $spots->unique()->count());
        $sheet = $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/stickers/printed')->json('data.0.url');
        $this->get($sheet)->assertOk()->assertSee('tag-office', false)->assertSee('সারি ১ · কলাম ১');

        $anis = Order::whereHas('customer.user', fn ($q) => $q->where('name', 'আনিস'))->firstOrFail();
        $this->actingAs($anis->customer->user, 'sanctum')->getJson('/api/v1/regular')->assertJsonPath('data.today.0.spot', 'সারি ১ · কলাম ১');

        $drop = OfficeDrop::firstOrFail();
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $k->id])->assertOk()
            ->assertJsonPath('data.kitchens.0.boxes.0.spot', 'সারি ১ · কলাম ১');
        Queue::fake();
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/deliver", [])->assertOk();
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->userId === $anis->customer->user_id && str_contains($job->body, 'সারি ১ · কলাম ১'));
    }

    public function test_a_later_box_keeps_earlier_spots_and_takes_the_next_one(): void
    {
        $office = Company::create(['name' => 'ABC', 'address' => 'লেভেল ৫', 'area_id' => $this->area->id]);
        $k = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $k2 = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->customer('জামিল', $k, $office);
        $this->cookPosts($k);
        $this->actingAs($this->shahidar, 'sanctum')->postJson('/api/v1/shahidar/stickers/print')->assertOk();

        $this->customer('আকাশ', $k2, $office);
        $this->cookPosts($k2);
        $this->postJson('/api/v1/shahidar/stickers/print')->assertOk()->assertJsonPath('data.count', 1);

        $spots = Order::with('customer.user')->get()->mapWithKeys(fn (Order $o) => [$o->customer->user->name => BoxLabels::spot($o)]);
        $this->assertSame('সারি ১ · কলাম ১', $spots['জামিল']);
        $this->assertSame('সারি ১ · কলাম ২', $spots['আকাশ']);
    }
}
