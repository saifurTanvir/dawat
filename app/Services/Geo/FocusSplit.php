<?php

namespace App\Services\Geo;

use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Models\ShahidarApplication;
use App\Support\DhakaTime;

/**
 * F92: the owner now focuses on Badda (the test operation). Every count is split in two:
 * Badda (the first launch zone) and everywhere else. By the area people chose, not their pin.
 */
class FocusSplit
{
    public const FOCUS = 'বাড্ডা (টেস্ট অপারেশন)';

    /** @return list<int> */
    public function focusAreaIds(): array
    {
        return Area::query()->whereIn('name', LaunchZones::GROUPS[self::FOCUS])->pluck('id')->all();
    }

    /** @return array{badda: array<string, int>, rest: array<string, int>} */
    public function counts(): array
    {
        $ids = $this->focusAreaIds();
        $today = DhakaTime::startOfDay();

        $kitchens = Kitchen::query()->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])
            ->get(['id', 'area_id', 'status', 'location', 'daily_plates', 'created_at']);
        $pinnedToday = PinLink::query()->where('submitted_at', '>=', $today)->distinct()->pluck('kitchen_id')->flip();
        $customers = Customer::query()->where('status', '!=', CustomerStatus::Stopped)->get(['id', 'area_id', 'created_at']);
        $applications = ShahidarApplication::query()->with('areas:id')->get(['id', 'created_at']);

        $side = function (bool $badda) use ($ids, $today, $kitchens, $pinnedToday, $customers, $applications): array {
            $mine = fn ($areaId) => in_array($areaId, $ids, true) === $badda;
            $k = $kitchens->filter(fn ($x) => $mine($x->area_id));
            $approved = $k->where('status', KitchenStatus::Approved);
            $c = $customers->filter(fn ($x) => $mine($x->area_id));
            $a = $applications->filter(fn ($x) => $x->areas->pluck('id')->intersect($ids)->isNotEmpty() === $badda);

            return [
                'cooks' => $approved->count(),
                'pending' => $k->where('status', KitchenStatus::Pending)->count(),
                'pinned' => $approved->filter(fn ($x) => $x->location !== null)->count(),
                'pinless' => $approved->filter(fn ($x) => $x->location === null)->count(),
                'plates' => (int) $approved->filter(fn ($x) => $x->location !== null)->sum('daily_plates'),
                // Approved ones only, matching the row it sits in ("গৃহিণী (অনুমোদিত)"); new sign-ups waiting are in "অপেক্ষায়".
                'cooks_today' => $approved->filter(fn ($x) => $x->created_at >= $today)->count(),
                'signups_today' => $k->filter(fn ($x) => $x->created_at >= $today)->count(),
                'pending_today' => $k->where('status', KitchenStatus::Pending)->filter(fn ($x) => $x->created_at >= $today)->count(),
                'pins_today' => $approved->filter(fn ($x) => $pinnedToday->has($x->id))->count(),
                'customers' => $c->count(),
                'customers_today' => $c->filter(fn ($x) => $x->created_at >= $today)->count(),
                'managers' => $a->count(),
                'managers_today' => $a->filter(fn ($x) => $x->created_at >= $today)->count(),
            ];
        };

        return ['badda' => $side(true), 'rest' => $side(false)];
    }
}
