<?php

namespace Tests\Feature\Kitchen;

use App\Enums\KitchenStatus;
use App\Filament\Pages\ZoneMap;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Widgets\PinReviews;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F68: the cook drops her home pin from a link, no login. */
class PinLinkTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = Area::factory()->create(['name' => 'মোহাম্মদপুর পরীক্ষা', 'center' => GeoPoint::make(23.7600, 90.3600), 'radius_m' => 2000]);
        $this->kitchen = Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => null, 'status' => KitchenStatus::Approved]);
    }

    private function near(float $metersNorth): array
    {
        return ['lat' => 23.7600 + $metersNorth / 111320, 'lng' => 90.3600];
    }

    public function test_page_explains_why_and_a_close_gps_pin_goes_straight_on_the_kitchen(): void
    {
        $link = PinLink::forKitchen($this->kitchen);
        $this->assertSame($link->id, PinLink::forKitchen($this->kitchen)->id, 'reuses a live link');
        $this->assertStringContainsString('আধা থেকে ১ কিলোমিটারের মধ্যে', $link->message());
        $this->assertStringContainsString($link->url(), $link->message());

        $this->get($link->url())->assertOk()
            ->assertSee('লোকেশন ছাড়া আপনাকে অনবোর্ড করা যাচ্ছে না')
            ->assertSee('আপনি কি এখন বাসায় আছেন?')
            // GPS failing (e.g. inside Messenger) must fall back to the map, not dead-end.
            ->assertSee('ফোনের লোকেশন পাওয়া যায়নি, সমস্যা নেই');

        $this->postJson(route('pin.store', $link->token), [...$this->near(500), 'mode' => 'home', 'accuracy' => 25])
            ->assertOk()->assertJson(['result' => 'saved']);

        $this->kitchen->refresh();
        $this->assertEqualsWithDelta(23.7600 + 500 / 111320, $this->kitchen->location->lat, 0.00001);
        $this->assertNotNull($this->kitchen->public_location);
        $this->assertSame('accepted', $link->fresh()->review);
        // The open link never shows the pin back.
        $this->get($link->url())->assertOk()->assertSee('আপনার লোকেশন পেয়েছি')->assertDontSee((string) round($this->kitchen->location->lat, 4));
    }

    public function test_rough_gps_is_refused_and_a_far_pin_waits_for_an_admin(): void
    {
        $link = PinLink::forKitchen($this->kitchen);

        $this->postJson(route('pin.store', $link->token), [...$this->near(300), 'mode' => 'home', 'accuracy' => 450])
            ->assertStatus(422)->assertJsonValidationErrors('accuracy');
        $this->postJson(route('pin.store', $link->token), [...$this->near(300), 'mode' => 'home'])
            ->assertStatus(422);

        // 6 km away — she was probably out when she tapped.
        $this->postJson(route('pin.store', $link->token), [...$this->near(6000), 'mode' => 'map'])
            ->assertOk()->assertJson(['result' => 'review']);
        $this->assertNull($this->kitchen->fresh()->location);
        $this->assertTrue(PinReviews::canView());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(PinReviews::class)->assertCanSeeTableRecords([$link])->callTableAction('accept', $link);
        $this->assertNotNull($this->kitchen->fresh()->location);
        $this->assertFalse(PinReviews::canView());
    }

    public function test_expired_and_unknown_links(): void
    {
        $link = PinLink::forKitchen($this->kitchen);
        $link->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get($link->url())->assertOk()->assertSee('মেয়াদ শেষ');
        $this->postJson(route('pin.store', $link->token), [...$this->near(100), 'mode' => 'map'])->assertStatus(422);
        $this->get('/pin/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_admin_makes_links_from_the_kitchen_and_the_zones_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewKitchen::class, ['record' => $this->kitchen->id])
            ->mountAction('pinLink')->assertHasNoActionErrors();
        $this->assertSame(1, PinLink::count());

        Livewire::test(ZoneMap::class)->callAction('pins')->assertSee('পিন ছাড়া গৃহিণী')->assertSee($this->kitchen->display_name)
            ->callAction('pinLinks')->assertHasNoActionErrors();
        $this->assertSame(1, PinLink::count());
    }
}
