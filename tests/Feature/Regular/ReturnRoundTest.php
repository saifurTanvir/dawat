<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Jobs\SendRoundSms;
use App\Models\AppNotification;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Post;
use App\Models\RoundStop;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Settlements;
use App\Services\Regular\OfficeDrops;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** F110: the evening return round — empty boxes back to each cook, paid at her door on her pay day. */
class ReturnRoundTest extends TestCase
{
    use RefreshDatabase;

    private User $shahidar;

    private Company $office;

    private Kitchen $weeklyOther;

    private Kitchen $weeklyToday;

    private Kitchen $daily;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false, 'dawat.rounds.sms' => true]);
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-24 12:00', 'Asia/Dhaka')); // a Tuesday
        $area = Area::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($area);
        $contact = User::factory()->create();
        $this->office = Company::create(['name' => 'ABC', 'address' => 'বাড়ি ১২', 'area_id' => $area->id, 'contact_user_id' => $contact->id]);
        $this->office->location = GeoPoint::make(23.7800, 90.4250);
        $this->office->save();

        $make = function (string $name, float $lat, array $pay) use ($area, $admin) {
            $k = Kitchen::factory()->approved()->at($lat, 90.4230)->create(['area_id' => $area->id, 'display_name' => $name] + $pay);
            $c = Customer::factory()->create(['area_id' => $area->id, 'company_id' => $this->office->id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 2, 'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'billing' => 'each']);
            app(PairCustomer::class)($c, $k, $this->shahidar, $admin);
            $post = Post::factory()->create(['kitchen_id' => $k->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => 4, 'plates_left' => 4, 'price_paisa' => 12000]);
            app(BookingPlanner::class)->bookForPost($post);

            return $k;
        };
        $this->weeklyOther = $make('বৃহস্পতিবারের রান্না', 23.7810, ['pay_cycle' => 'weekly', 'pay_weekday' => 4]);
        $this->weeklyToday = $make('মঙ্গলবারের রান্না', 23.7830, ['pay_cycle' => 'weekly', 'pay_weekday' => 2]);
        $this->daily = $make('রোজকার রান্না', 23.7850, ['pay_cycle' => 'daily']);

        // Lunch: picked up and handed to the office, cash taken at the office.
        $drop = $this->drop();
        foreach ([$this->weeklyOther, $this->weeklyToday, $this->daily] as $k) {
            app(OfficeDrops::class)->pickup($drop, $k, $this->shahidar, null);
        }
        app(OfficeDrops::class)->deliver($drop, $this->shahidar, null, true);
        $this->travelTo(Carbon::parse('2026-11-24 18:00', 'Asia/Dhaka'));
    }

    private function drop(): OfficeDrop
    {
        return OfficeDrop::where('company_id', $this->office->id)->firstOrFail();
    }

    private function stop(Kitchen $k): RoundStop
    {
        return RoundStop::where('kind', 'return')->where('kitchen_id', $k->id)->firstOrFail();
    }

    public function test_boxes_go_back_house_by_house_and_each_cook_is_paid_on_her_pay_day_from_the_cash_he_holds(): void
    {
        $drop = $this->drop();
        $money = app(Settlements::class);
        $held = $money->cashHeldBy($this->shahidar);
        $this->assertSame(3 * 24000, $held); // 2 plates × ৳120 per box, three boxes
        $owed = fn (Kitchen $k) => $money->dueFor($k)['payable'];
        $this->assertGreaterThan(0, $owed($this->daily));
        $base = "/api/v1/shahidar/drops/{$drop->id}/return";

        // The plan: what to give and pay at each house.
        $plan = $this->actingAs($this->shahidar)->getJson($base)->assertOk()->json('data');
        $this->assertSame('return', $plan['kind']);
        $rows = collect($plan['stops'])->keyBy('kitchen_id');
        $this->assertSame(0, $rows[$this->weeklyOther->id]['pay_due_paisa']);
        $this->assertSame('প্রতি বৃহস্পতিবার', $rows[$this->weeklyOther->id]['pay_label']);
        $this->assertSame($owed($this->weeklyToday), $rows[$this->weeklyToday->id]['pay_due_paisa']);
        $this->assertSame(1, $rows[$this->daily->id]['boxes']);

        // Sets out from the office (no phone location): the nearest house to the office first.
        $r = $this->postJson("{$base}/start")->assertOk()->json('data');
        $first = collect($r['stops'])->firstWhere('id', $r['next_id']);
        $this->assertSame($this->weeklyOther->id, $first['kitchen_id']);
        $told = AppNotification::where('user_id', $this->weeklyOther->user_id)->latest('id')->first();
        $this->assertSame('এলাকা ম্যানেজার এখন বক্স ফেরত দিতে আসছেন', $told->title);
        $this->assertStringContainsString('আপনার টাকা প্রতি বৃহস্পতিবার', $told->body);
        $soon = AppNotification::where('user_id', $this->daily->user_id)->latest('id')->first();
        $this->assertStringContainsString('আজ পাবেন ৳', $soon->body);
        Queue::assertPushed(SendRoundSms::class); // no app notifications on their phones

        // Not her pay day: boxes only.
        $dueDaily = $owed($this->daily);
        $dueTuesday = $owed($this->weeklyToday);
        $this->postJson("{$base}/stops/{$this->stop($this->weeklyOther)->id}/done")->assertOk();
        $this->assertSame(0, $this->stop($this->weeklyOther)->paid_paisa);
        $this->assertGreaterThan(0, $owed($this->weeklyOther));
        // Her pay day and the daily cook: paid everything they are owed.
        $this->postJson("{$base}/stops/{$this->stop($this->weeklyToday)->id}/done")->assertOk();
        $this->postJson("{$base}/stops/{$this->stop($this->daily)->id}/done")->assertOk();
        $this->assertSame($dueTuesday, $this->stop($this->weeklyToday)->paid_paisa);
        $this->assertSame($dueDaily, $this->stop($this->daily)->paid_paisa);
        $this->assertSame(0, $owed($this->daily));
        $this->postJson("{$base}/stops/{$this->stop($this->daily)->id}/done")->assertStatus(422); // not twice

        // What he paid comes off the cash he holds.
        $done = $this->getJson($base)->json('data');
        $this->assertSame($dueDaily + $dueTuesday, $done['paid_today_paisa']);
        $this->assertSame($held - $dueDaily - $dueTuesday, $done['cash_held_paisa']);
        $this->assertNull($done['next_id']);

        // The cook confirms — or says she did not get it.
        $mine = $this->actingAs($this->daily->user)->getJson('/api/v1/my/rounds')->assertOk()->json('data');
        $this->assertTrue($mine[0]['done']);
        $this->assertSame($dueDaily, $mine[0]['paid_paisa']);
        $this->postJson("/api/v1/my/rounds/{$mine[0]['id']}/confirm", ['got' => true])->assertOk();
        $this->assertNotNull($this->stop($this->daily)->cook_confirmed_at);
        $this->actingAs($this->weeklyToday->user)->postJson("/api/v1/my/rounds/{$this->stop($this->weeklyToday)->id}/confirm", ['got' => false])->assertOk();
        $this->assertTrue(AppNotification::where('user_id', $this->shahidar->id)->where('title', 'like', '%পাননি')->exists());
        $this->actingAs($this->daily->user)->postJson("/api/v1/my/rounds/{$this->stop($this->weeklyToday)->id}/confirm", ['got' => true])->assertNotFound();

        // Handing his cash to Dawat: what he paid cooks is already taken off.
        $admin = User::factory()->admin()->create();
        $s = $money->receiveFromShahidar($this->shahidar, $admin);
        $this->assertSame($held - $dueDaily - $dueTuesday, $s->amount_paisa);
        $this->assertSame($dueDaily + $dueTuesday, $s->cook_paid_paisa);
        $this->assertSame(0, $money->cashHeldBy($this->shahidar));
        $this->assertSame(2, Settlement::where('offset_settlement_id', $s->id)->count());
    }

    public function test_the_return_round_waits_until_the_office_has_its_boxes_and_cooks_choose_when_they_are_paid(): void
    {
        // Boxes still on the way to another office: no return round yet.
        $drop = $this->drop();
        $drop->orders()->first()->forceFill(['status' => OrderStatus::PickedUp])->save();
        $this->actingAs($this->shahidar)->postJson("/api/v1/shahidar/drops/{$drop->id}/return/start")->assertStatus(422);

        $this->actingAs($this->daily->user)->getJson('/api/v1/my/pay-schedule')->assertOk()->assertJsonPath('data.label', 'প্রতিদিন');
        $this->putJson('/api/v1/my/pay-schedule', ['pay_cycle' => 'weekly'])->assertUnprocessable();
        $this->putJson('/api/v1/my/pay-schedule', ['pay_cycle' => 'weekly', 'pay_weekday' => 5])->assertOk()
            ->assertJsonPath('data.label', 'প্রতি শুক্রবার');
        $this->assertSame(5, $this->daily->fresh()->pay_weekday);
    }
}
