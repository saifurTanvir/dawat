<?php

namespace Tests\Feature\Admin;

use App\Enums\KitchenStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F58: add a customer, a cook or a shahidar from the dashboard. */
class QuickAddTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        $this->area = Area::factory()->create(['is_active' => true]);
    }

    public function test_dashboard_shows_the_three_buttons_and_the_voice_typing_script(): void
    {
        $this->get('/admin')->assertOk()
            ->assertSee('গ্রাহক যোগ করুন')->assertSee('রাঁধুনি যোগ করুন')->assertSee('শাহিদার যোগ করুন')
            ->assertSee('webkitSpeechRecognition', false);
    }

    public function test_add_a_cook_pending_without_a_location(): void
    {
        Livewire::test(Dashboard::class)
            ->callAction('addCook', [
                'phone' => '০১৭১১২২৩৩৪৪', 'name' => 'রহিমা বেগম', 'display_name' => 'রহিমা খালার রান্নাঘর',
                'area_id' => $this->area->id, 'pickup_address' => 'বাসা ৫, রোড ২',
            ])
            ->assertHasNoActionErrors();

        $kitchen = Kitchen::sole();
        $this->assertSame('01711223344', $kitchen->user->phone);
        $this->assertSame('রহিমা বেগম', $kitchen->user->name);
        $this->assertTrue($kitchen->user->hasRole(Roles::COOK));
        $this->assertSame(KitchenStatus::Pending, $kitchen->status);
        $this->assertNull($kitchen->location);
    }

    public function test_add_a_cook_with_a_map_link_and_approve_at_once(): void
    {
        Livewire::test(Dashboard::class)
            ->callAction('addCook', [
                'phone' => '01711223344', 'name' => 'সুমা', 'display_name' => 'সুমার রান্না', 'area_id' => $this->area->id,
                'where' => 'https://maps.google.com/?q=23.7812,90.4256', 'approve' => true,
            ])
            ->assertHasNoActionErrors();

        $kitchen = Kitchen::sole();
        $this->assertTrue($kitchen->isApproved());
        $this->assertEqualsWithDelta(23.7812, $kitchen->location->lat, 0.0001);
        $this->assertNotNull($kitchen->public_location);

        // The same number cannot get a second kitchen.
        Livewire::test(Dashboard::class)
            ->callAction('addCook', ['phone' => '01711223344', 'name' => 'সুমা', 'display_name' => 'আরেকটা', 'area_id' => $this->area->id])
            ->assertHasActionErrors(['phone']);
    }

    public function test_add_a_customer_and_a_shahidar(): void
    {
        Livewire::test(Dashboard::class)
            ->callAction('addCustomer', [
                'phone' => '01912345678', 'name' => 'জামান সাহেব', 'area_id' => $this->area->id,
                'address' => 'বাসা ১২, রোড ৩, ফ্ল্যাট ৪বি', 'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4],
            ])
            ->assertHasNoActionErrors();
        $this->assertSame('01912345678', Customer::sole()->user->phone);

        Livewire::test(Dashboard::class)
            ->callAction('addShahidar', ['phone' => '01812345678', 'name' => 'করিম', 'area_ids' => [$this->area->id]])
            ->assertHasNoActionErrors();
        $shahidar = User::where('phone', '01812345678')->sole();
        $this->assertTrue($shahidar->isShahidar());
        $this->assertSame([$this->area->id], $shahidar->shahidarAreas()->pluck('areas.id')->all());
    }
}
