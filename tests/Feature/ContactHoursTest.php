<?php

namespace Tests\Feature;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use App\Support\ContactHours;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** F41: people choose when they may be called; Dawat keeps the delivery window open. */
class ContactHoursTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse("2026-11-10 {$time}", 'Asia/Dhaka'));
    }

    public function test_default_hours_and_bangla_label(): void
    {
        $user = User::factory()->create();
        $this->at('08:30');
        $this->assertFalse(ContactHours::okNow($user));
        $this->at('09:00');
        $this->assertTrue(ContactHours::okNow($user));
        $this->assertSame('সকাল ৯টা – রাত ৯টা', ContactHours::label($user));
        $this->assertSame('দুপুর ১:৩০', ContactHours::clock('13:30'));
    }

    public function test_a_person_sets_their_hours_within_dawats_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/contact-hours', ['from' => '06:00', 'to' => '12:00', 'prefer' => 'any'])
            ->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/contact-hours', ['from' => '10:00', 'to' => '11:00', 'prefer' => 'any'])
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/contact-hours', ['from' => '15:00', 'to' => '20:00', 'prefer' => 'whatsapp'])
            ->assertOk()
            ->assertJsonPath('data.contact.label', 'বিকেল ৩টা – রাত ৮টা')
            ->assertJsonPath('data.contact.prefer', 'whatsapp');

        $this->getJson('/api/v1/me')->assertJsonPath('data.contact.from', '15:00');
    }

    public function test_the_delivery_window_stays_open_on_a_box_day(): void
    {
        $user = User::factory()->create(['call_from' => '17:00', 'call_to' => '21:00']);
        $this->at('12:30');
        $this->assertFalse(ContactHours::okNow($user));
        $this->assertTrue(ContactHours::okNow($user, [MealSlot::Lunch]));
        $this->assertSame('meal', ContactHours::reason($user, [MealSlot::Lunch]));
        $this->assertFalse(ContactHours::okNow($user, [MealSlot::Dinner]));
    }

    public function test_shahidar_sees_whether_to_call_and_a_whatsapp_link_without_coordinates(): void
    {
        $this->at('08:00');
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $kitchen = Kitchen::factory()->approved()->create();
        $customer = Customer::factory()->create();
        $customer->user->update(['phone' => '01712345678', 'contact_prefer' => 'whatsapp']);
        Order::create([
            'service_date' => '2026-11-10', 'seq' => 1, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $kitchen->id,
            'customer_id' => $customer->id, 'shahidar_id' => $shahidar->id, 'plates' => 1, 'food_paisa' => 15000,
            'delivery_fee_paisa' => 2000, 'status' => OrderStatus::Booked,
        ]);

        $row = $this->actingAs($shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')->assertOk()->json('data.orders.0');

        $this->assertFalse($row['customer']['contact']['ok_now']);
        $this->assertSame('সকাল ৯টা – রাত ৯টা', $row['customer']['contact']['hours']);
        $this->assertSame('whatsapp', $row['customer']['contact']['prefer']);
        $this->assertStringStartsWith('https://wa.me/8801712345678?text=', $row['customer']['contact']['whatsapp_url']);
        $this->assertArrayHasKey('contact', $row['kitchen']);
        $this->assertStringNotContainsString('"lat"', json_encode($row));
        $this->assertStringNotContainsString('"location"', json_encode($row));
    }

    public function test_admin_sees_call_status_on_the_customer(): void
    {
        $this->at('22:00');
        $this->actingAs(User::factory()->admin()->create());
        $customer = Customer::factory()->create();

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertSee('এখন কল নয়, হোয়াটসঅ্যাপে মেসেজ দিন')
            ->assertSee('wa.me', false);
    }
}
