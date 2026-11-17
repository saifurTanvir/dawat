<?php

namespace Tests\Feature\Admin;

use App\Enums\KitchenStatus;
use App\Filament\Pages\ZoneMap;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Services\Geo\Zones;
use App\Support\Geo\GeoPoint;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F67: 1 km zones with cooks, customers and shahidars. */
class ZoneMapTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        // Far from the seeded Dhaka areas so nothing else is nearer.
        $this->area = Area::factory()->create(['name' => 'পরীক্ষা', 'center' => GeoPoint::make(22.35, 91.80), 'radius_m' => 2000, 'sort_order' => 0]);
    }

    public function test_a_two_km_area_gets_seven_named_zones_that_cover_it(): void
    {
        $zones = collect(app(Zones::class)->forArea($this->area));

        $this->assertCount(7, $zones);
        $this->assertSame('পরীক্ষা কেন্দ্র', $zones[0]['name']);
        $this->assertEqualsCanonicalizing(
            ['পরীক্ষা কেন্দ্র', 'পরীক্ষা উত্তর', 'পরীক্ষা উত্তর-পূর্ব', 'পরীক্ষা দক্ষিণ-পূর্ব', 'পরীক্ষা দক্ষিণ', 'পরীক্ষা দক্ষিণ-পশ্চিম', 'পরীক্ষা উত্তর-পশ্চিম'],
            $zones->pluck('name')->all(),
        );

        // Every point of the area is within 1 km of some zone centre.
        $c = $this->area->center;
        foreach (range(0, 345, 15) as $deg) {
            foreach ([300, 900, 1500, 1990] as $m) {
                $p = GeoPoint::make($c->lat + $m * cos(deg2rad($deg)) / 111320, $c->lng + $m * sin(deg2rad($deg)) / (111320 * cos(deg2rad($c->lat))));
                $this->assertLessThanOrEqual(1001, $zones->min(fn ($z) => $p->distanceTo($z['center'])), "gap at {$deg}° {$m} m");
            }
        }

        $small = Area::factory()->create(['radius_m' => 900]);
        $this->assertCount(7, app(Zones::class)->forArea(Area::factory()->create(['radius_m' => 1500])));
        $this->assertCount(1, app(Zones::class)->forArea($small));
    }

    public function test_counts_cooks_customers_and_shahidars_per_zone(): void
    {
        $c = $this->area->center;
        $north = GeoPoint::make($c->lat + 1700 / 111320, $c->lng);
        Kitchen::factory()->count(3)->create(['area_id' => $this->area->id, 'location' => $north, 'status' => KitchenStatus::Approved]);
        Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => $c, 'status' => KitchenStatus::Pending]);
        Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => null, 'status' => KitchenStatus::Approved]);
        Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => $c, 'status' => KitchenStatus::Rejected]);
        $customer = Customer::factory()->create(['area_id' => $this->area->id]);
        $customer->location = $c;
        $customer->save();
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $this->area->shahidars()->attach($shahidar);

        $report = app(Zones::class)->report();
        $a = $report['areas']->firstWhere('id', $this->area->id);
        $zones = $a['zones']->keyBy('name');

        $this->assertSame(3, $zones['পরীক্ষা উত্তর']['cooks']);
        $this->assertSame(1, $zones['পরীক্ষা কেন্দ্র']['pending']);
        $this->assertSame(1, $zones['পরীক্ষা কেন্দ্র']['customers']);
        $this->assertSame(1, $a['unpinned']['cooks']);
        $this->assertSame(4, $a['cooks']);
        $this->assertSame(1, $a['shahidars']);
        $this->assertSame($this->area->id, $report['areas']->first()['id'], 'busiest area first');
        $this->assertSame(1, $report['totals']['zones_ready']);
    }

    public function test_admin_sees_the_page_and_others_do_not(): void
    {
        Kitchen::factory()->create(['area_id' => $this->area->id, 'location' => $this->area->center, 'status' => KitchenStatus::Approved]);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ZoneMap::class)->assertOk()->assertSee('পরীক্ষা কেন্দ্র')->assertSee('আরও গৃহিণী লাগবে')
            ->callAction('csv')->assertHasNoActionErrors();
        $this->get('/admin/zones')->assertOk();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/zones')->assertForbidden();
    }
}
