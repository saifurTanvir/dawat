<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Filament\Resources\Companies\CompanyResource;
use App\Jobs\SendRoundSms;
use App\Models\AppNotification;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Post;
use App\Models\RoundStop;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** F95: the area manager's office round — house by house on the map, each cook told before he comes. */
class OfficeRoundTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $shahidar;

    private User $admin;

    private Company $office;

    private Kitchen $near;

    private Kitchen $next;

    private Kitchen $far;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false, 'dawat.rounds.sms' => true]);
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-24 11:30', 'Asia/Dhaka'));
        $this->area = Area::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
        $contact = User::factory()->create(['name' => 'রিসেপশন']);
        $this->office = Company::create(['name' => 'ABC সফটওয়্যার', 'address' => 'লেভেল ৫, বাড়ি ১২', 'area_id' => $this->area->id, 'contact_user_id' => $contact->id]);
        $this->office->location = GeoPoint::make(23.7800, 90.4250);
        $this->office->save();

        $this->near = $this->cook('রিনার রান্না', 23.7810, 90.4200);
        $this->next = $this->cook('সুমার রান্নাঘর', 23.7850, 90.4230);
        $this->far = $this->cook('দূরের রান্না', 23.8700, 90.4250); // ~10 km: about an hour away
    }

    private function cook(string $name, float $lat, float $lng): Kitchen
    {
        $kitchen = Kitchen::factory()->approved()->at($lat, $lng)->create(['area_id' => $this->area->id, 'display_name' => $name, 'pickup_address' => $name.'-এর বাসা']);
        $c = Customer::factory()->create(['area_id' => $this->area->id, 'company_id' => $this->office->id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        app(PairCustomer::class)($c, $kitchen, $this->shahidar, $this->admin);
        $post = Post::factory()->create(['kitchen_id' => $kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 4, 'plates_left' => 4]);
        app(BookingPlanner::class)->bookForPost($post);

        return $kitchen;
    }

    private function drop(): OfficeDrop
    {
        return OfficeDrop::where('company_id', $this->office->id)->firstOrFail();
    }

    private function told(Kitchen $kitchen): array
    {
        return AppNotification::where('user_id', $kitchen->user_id)->where('tag', 'like', 'round-%')->pluck('title')->all();
    }

    public function test_the_round_starts_at_the_nearest_house_tells_cooks_in_turn_and_ends_at_the_office(): void
    {
        $drop = $this->drop();
        $base = "/api/v1/shahidar/drops/{$drop->id}/round";

        // Before leaving: the plan, with the way to every house; nobody told yet.
        $plan = $this->actingAs($this->shahidar)->getJson($base)->assertOk()->json('data');
        $this->assertFalse($plan['started']);
        $this->assertCount(3, $plan['stops']);
        $this->assertStringContainsString('destination=23.781000,90.420000', collect($plan['stops'])->firstWhere('kitchen_id', $this->near->id)['map_url']);
        $this->assertStringContainsString('destination=23.780000,90.425000', $plan['office']['map_url']);
        $this->assertSame([], $this->told($this->near));

        // "রওনা দিচ্ছি" next to the first cook's house.
        $r = $this->postJson("{$base}/start", ['lat' => 23.7805, 'lng' => 90.4195])->assertOk()->json('data');
        $this->assertTrue($r['started']);
        $this->assertSame($this->near->id, collect($r['stops'])->firstWhere('id', $r['next_id'])['kitchen_id']);
        $this->assertSame(['এলাকা ম্যানেজার এখন আপনার কাছে আসছেন'], $this->told($this->near));
        $this->assertCount(1, $this->told($this->next)); // within 30 minutes: "coming at about…"
        $this->assertStringStartsWith('এলাকা ম্যানেজার আসছেন: আনুমানিক', $this->told($this->next)[0]);
        $this->assertSame([], $this->told($this->far)); // an hour away: not yet
        // No app notifications on their phones: an SMS each.
        Queue::assertPushed(SendRoundSms::class, 2);

        // Boxes picked up at the first house: the next cook hears he is on his way.
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $this->near->id])->assertOk();
        $this->assertContains('এলাকা ম্যানেজার এখন আপনার কাছে আসছেন', $this->told($this->next));
        $this->assertNotNull(RoundStop::where('kitchen_id', $this->near->id)->value('picked_at'));

        // Time passes: the far cook's turn comes within 30 minutes.
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $this->next->id])->assertOk();
        $this->travel(20)->minutes();
        $this->artisan('rounds:tick')->assertSuccessful();
        $this->assertNotEmpty($this->told($this->far));

        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $this->far->id])->assertOk();
        $done = $this->getJson($base)->assertOk()->json('data');
        $this->assertNull($done['next_id']); // now: the office
        $this->assertSame(3, $done['picked']);
    }

    public function test_a_cook_who_needs_ten_more_minutes_waits_her_turn_and_the_manager_hears(): void
    {
        $drop = $this->drop();
        $this->actingAs($this->shahidar)->postJson("/api/v1/shahidar/drops/{$drop->id}/round/start", ['lat' => 23.7805, 'lng' => 90.4195])->assertOk();

        // The first cook sees when he comes — only boxes, no office or customer names.
        $mine = $this->actingAs($this->near->user)->getJson('/api/v1/my/rounds')->assertOk()->json('data');
        $this->assertCount(1, $mine);
        $this->assertTrue($mine[0]['coming_now']);
        $this->assertStringNotContainsString('ABC', json_encode($mine, JSON_UNESCAPED_UNICODE));

        $this->postJson("/api/v1/my/rounds/{$mine[0]['id']}/reply", ['reply' => 'late'])->assertOk();
        $this->assertSame('late', RoundStop::find($mine[0]['id'])->cook_reply);
        $this->assertTrue(AppNotification::where('user_id', $this->shahidar->id)->where('title', 'like', '%আরও ১০ মিনিট লাগবে')->exists());

        // So the second house comes first now, and that cook is told he is on his way.
        $r = $this->actingAs($this->shahidar)->getJson("/api/v1/shahidar/drops/{$drop->id}/round")->json('data');
        $this->assertSame($this->next->id, collect($r['stops'])->firstWhere('id', $r['next_id'])['kitchen_id']);
        $this->assertContains('এলাকা ম্যানেজার এখন আপনার কাছে আসছেন', $this->told($this->next));

        // "পরে আসব" puts a house at the end.
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/round/stops/{$r['next_id']}/later")->assertOk();
        $this->assertSame(3, RoundStop::find($r['next_id'])->fresh()->position);

        // Someone else's stop cannot be answered.
        $this->actingAs($this->far->user)->postJson("/api/v1/my/rounds/{$mine[0]['id']}/reply", ['reply' => 'ready'])->assertNotFound();
    }

    public function test_only_the_offices_area_manager_sees_the_round_and_admins_set_the_office_pin(): void
    {
        $drop = $this->drop();
        $other = User::factory()->create();
        $other->assignRole(Roles::SHAHIDAR);
        $this->actingAs($other)->getJson("/api/v1/shahidar/drops/{$drop->id}/round")->assertNotFound();
        $this->actingAs($this->near->user)->getJson("/api/v1/shahidar/drops/{$drop->id}/round")->assertForbidden();

        $data = CompanyResource::withContact(['name' => 'X', 'contact_phone' => null, 'contact_name' => null, 'where' => '23.7701, 90.4102']);
        $this->assertEqualsWithDelta(23.7701, $data['location']->lat, 0.0001);
        $this->assertArrayNotHasKey('location', $this->office->fresh()->toArray());
    }
}
