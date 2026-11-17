<?php

namespace App\Services\Geo;

use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Support\Geo\GeoPoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F67: every active area cut into 1 km circles ("zones"), with how many
 * cooks, customers and shahidars each has — for deciding where to sell.
 *
 * Zones are a hex grid around the area's centre (spacing √3·r, so the
 * circles leave no gaps): a 2 km area gets the centre plus six around it.
 * Each pinned cook or customer counts once, in the nearest zone centre.
 * Those without a pin count on their area's "পিন ছাড়া" line.
 */
final class Zones
{
    public const RADIUS_M = 1000;

    private const M_PER_DEG_LAT = 111320.0;

    /** Ring positions at these bearings, clockwise from north. */
    private const DIRECTIONS = [0 => 'উত্তর', 60 => 'উত্তর-পূর্ব', 120 => 'দক্ষিণ-পূর্ব', 180 => 'দক্ষিণ', 240 => 'দক্ষিণ-পশ্চিম', 300 => 'উত্তর-পশ্চিম'];

    /**
     * @return Collection<int, array{key: string, area_id: int, area: string, name: string, center: GeoPoint}>
     */
    public function zones(): Collection
    {
        return Area::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (Area $a) => $a->center !== null)
            ->flatMap(fn (Area $a) => $this->forArea($a))
            ->values();
    }

    /** @return list<array{key: string, area_id: int, area: string, name: string, center: GeoPoint}> */
    public function forArea(Area $area): array
    {
        $r = self::RADIUS_M;
        $step = sqrt(3) * $r;
        $reach = max((int) $area->radius_m, 1);
        $n = (int) ceil($reach / $step) + 1;
        $cosLat = cos(deg2rad($area->center->lat));

        $out = [];
        // Axial hex coordinates (q, s); basis vectors point north and north-east (60°).
        for ($q = -$n; $q <= $n; $q++) {
            for ($s = -$n; $s <= $n; $s++) {
                $north = $step * ($q + $s * cos(deg2rad(60)));
                $east = $step * ($s * sin(deg2rad(60)));
                $dist = hypot($north, $east);
                // A ring is kept while its circles reach at least half a radius past the area's edge,
                // so a 1.5 km area gets 7 zones and a 1 km area just one.
                if ($dist > $reach + $r / 2 + 1) {
                    continue;
                }
                $out[] = [
                    'dist' => $dist,
                    'bearing' => fmod(rad2deg(atan2($east, $north)) + 360, 360),
                    'center' => GeoPoint::make(
                        $area->center->lat + $north / self::M_PER_DEG_LAT,
                        $area->center->lng + $east / (self::M_PER_DEG_LAT * $cosLat),
                    ),
                ];
            }
        }
        usort($out, fn ($a, $b) => [round($a['dist']), $a['bearing']] <=> [round($b['dist']), $b['bearing']]);

        $used = [];

        return array_map(function (array $z, int $i) use ($area, &$used) {
            $name = $z['dist'] < 1 ? 'কেন্দ্র' : $this->direction($z['bearing']);
            if ($z['dist'] > sqrt(3) * self::RADIUS_M * 1.5) {
                $name = 'দূর '.$name;
            }
            $used[$name] = ($used[$name] ?? 0) + 1;
            if ($used[$name] > 1) {
                $name .= ' '.self::bn($used[$name]);
            }

            return [
                'key' => $area->id.'-'.$i,
                'area_id' => $area->id,
                'area' => $area->name,
                'name' => $area->name.' '.$name,
                'center' => $z['center'],
            ];
        }, $out, array_keys($out));
    }

    /**
     * Zones with counts, grouped by area, busiest areas first.
     *
     * @return array{areas: Collection, totals: array<string, int>}
     */
    public function report(): array
    {
        $zones = $this->zones()->map(fn (array $z) => $z + ['cooks' => 0, 'pending' => 0, 'customers' => 0])->keyBy('key');
        $unpinned = [];
        $totals = ['cooks' => 0, 'pending' => 0, 'customers' => 0, 'outside' => 0, 'no_area' => 0];

        $kitchens = Kitchen::query()->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->get(['id', 'area_id', 'location', 'status']);
        foreach ($kitchens as $k) {
            $field = $k->status === KitchenStatus::Approved ? 'cooks' : 'pending';
            $this->place($zones, $unpinned, $totals, $k->location, $k->area_id, $field);
        }
        $customers = Customer::query()->where('status', '!=', CustomerStatus::Stopped)->get(['id', 'area_id', 'location']);
        foreach ($customers as $c) {
            $this->place($zones, $unpinned, $totals, $c->location, $c->area_id, 'customers');
        }

        $shahidars = Area::query()->where('is_active', true)->withCount('shahidars')->pluck('shahidars_count', 'id');

        $areas = $zones->groupBy('area_id')->map(function (Collection $zs, int $areaId) use ($unpinned, $shahidars) {
            $loose = $unpinned[$areaId] ?? ['cooks' => 0, 'pending' => 0, 'customers' => 0];

            return [
                'id' => $areaId,
                'name' => $zs->first()['area'],
                'zones' => $zs->values(),
                'unpinned' => $loose,
                'shahidars' => (int) ($shahidars[$areaId] ?? 0),
                'cooks' => $zs->sum('cooks') + $loose['cooks'],
                'pending' => $zs->sum('pending') + $loose['pending'],
                'customers' => $zs->sum('customers') + $loose['customers'],
            ];
        })->sortByDesc(fn ($a) => [$a['cooks'] + $a['pending'], $a['customers']])->values();

        $totals['zones'] = $zones->count();
        $totals['zones_with_cooks'] = $zones->filter(fn ($z) => $z['cooks'] > 0)->count();
        $totals['zones_ready'] = $zones->filter(fn ($z) => $z['cooks'] >= 3)->count();
        $totals['shahidars'] = (int) DB::table('area_shahidar')->join('areas', 'areas.id', '=', 'area_shahidar.area_id')->where('areas.is_active', true)->distinct()->count('area_shahidar.user_id');

        return ['areas' => $areas, 'totals' => $totals];
    }

    /** Nearest zone for a pin; else the area's "no pin" line; else counted as outside. */
    private function place(Collection $zones, array &$unpinned, array &$totals, ?GeoPoint $at, ?int $areaId, string $field): void
    {
        $totals[$field]++;
        if ($at) {
            $best = null;
            $bestD = INF;
            foreach ($zones as $key => $z) {
                $d = $at->distanceTo($z['center']);
                if ($d < $bestD) {
                    [$best, $bestD] = [$key, $d];
                }
            }
            if ($best !== null && $bestD <= self::RADIUS_M * 1.5) {
                $zones->put($best, array_merge($zones[$best], [$field => $zones[$best][$field] + 1]));

                return;
            }
            $totals['outside']++;

            return;
        }
        if ($areaId) {
            $unpinned[$areaId] ??= ['cooks' => 0, 'pending' => 0, 'customers' => 0];
            $unpinned[$areaId][$field]++;

            return;
        }
        $totals['no_area']++;
    }

    private function direction(float $bearing): string
    {
        $snap = (int) (round($bearing / 60) * 60) % 360;

        return self::DIRECTIONS[$snap];
    }

    public static function bn(int|string $n): string
    {
        return strtr((string) $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
