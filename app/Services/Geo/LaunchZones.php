<?php

namespace App\Services\Geo;

use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use Illuminate\Support\Collection;

/**
 * F84: the zones we may start operations in — a few neighbouring areas each.
 * Grouped by the cook's chosen area (not her pin), so cooks without a pin count too.
 */
class LaunchZones
{
    /** Zone name => area names, exactly as in the areas table. */
    public const GROUPS = [
        'বাড্ডা (টেস্ট অপারেশন)' => ['উত্তর বাড্ডা', 'মধ্য বাড্ডা', 'দক্ষিণ বাড্ডা', 'মেরুল বাড্ডা', 'শাহজাদপুর'],
        'পূর্ব (খিলগাঁও–বনশ্রী–নন্দীপাড়া)' => ['খিলগাঁও', 'বনশ্রী', 'নন্দীপাড়া', 'বাসাবো', 'রামপুরা', 'আফতাবনগর', 'মালিবাগ', 'মুগদা', 'মেরাদিয়া', 'গোড়ান', 'সবুজবাগ', 'মান্ডা', 'শান্তিনগর', 'শাহজাহানপুর'],
        'মিরপুর' => ['মিরপুর ১', 'মিরপুর ২', 'মিরপুর ৬', 'মিরপুর ১০', 'মিরপুর ১১', 'মিরপুর ১২', 'পল্লবী', 'কাজীপাড়া', 'শেওড়াপাড়া'],
        'মোহাম্মদপুর–ধানমন্ডি' => ['মোহাম্মদপুর', 'আদাবর', 'হাজারীবাগ', 'ধানমন্ডি', 'লালবাগ', 'কল্যাণপুর', 'রায়ের বাজার', 'লালমাটিয়া', 'কলাবাগান', 'শ্যামলী', 'আজিমপুর'],
        'যাত্রাবাড়ী–শনির আখড়া' => ['যাত্রাবাড়ী', 'শনির আখড়া', 'শ্যামপুর', 'কদমতলী', 'ধোলাইপাড়', 'ডেমরা', 'সাইনবোর্ড'],
        'গুলশান–বাড্ডা–বসুন্ধরা' => ['গুলশান ১', 'গুলশান ২', 'বনানী', 'উত্তর বাড্ডা', 'মধ্য বাড্ডা', 'দক্ষিণ বাড্ডা', 'মেরুল বাড্ডা', 'শাহজাদপুর', 'ভাটারা', 'বসুন্ধরা আবাসিক এলাকা', 'নদ্দা', 'মহাখালী', 'নিকেতন'],
        'উত্তরা–দক্ষিণখান–উত্তরখান' => ['উত্তরা (সেক্টর ১–৭)', 'উত্তরা (সেক্টর ৮–১৪)', 'দক্ষিণখান', 'উত্তরখান', 'আশকোনা', 'খিলক্ষেত', 'নিকুঞ্জ'],
    ];

    public const COOKS_GOAL = 70;

    /** @return Collection<int, array{key: int, name: string, areas: list<string>, cooks: int, pending: int, pinned: int, pinless: int, plates: int}> */
    public function summary(): Collection
    {
        $areaIds = Area::query()->pluck('id', 'name');
        $kitchens = Kitchen::query()->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])
            ->whereNotNull('area_id')->get(['id', 'area_id', 'status', 'location', 'daily_plates']);

        // F89: customers (not stopped) in each zone, by the area they chose.
        $byArea = Customer::query()->where('status', '!=', CustomerStatus::Stopped)
            ->whereNotNull('area_id')->selectRaw('area_id, count(*) as n')->groupBy('area_id')->pluck('n', 'area_id');
        $customers = collect(self::GROUPS)->map(fn ($areas) => collect($areas)->sum(fn ($a) => (int) ($byArea[$areaIds[$a] ?? 0] ?? 0)));

        return collect(self::GROUPS)->keys()->values()->map(function (string $name, int $i) use ($areaIds, $kitchens, $customers) {
            $ids = collect(self::GROUPS[$name])->map(fn ($a) => $areaIds[$a] ?? null)->filter()->all();
            $in = $kitchens->whereIn('area_id', $ids);
            $approved = $in->where('status', KitchenStatus::Approved);

            return [
                'key' => $i,
                'name' => $name,
                'areas' => self::GROUPS[$name],
                'cooks' => $approved->count(),
                'pending' => $in->where('status', KitchenStatus::Pending)->count(),
                'pinned' => $approved->filter(fn ($k) => $k->location !== null)->count(),
                'pinless' => $approved->filter(fn ($k) => $k->location === null)->count(),
                'plates' => (int) $approved->filter(fn ($k) => $k->location !== null)->sum('daily_plates'),
                'customers' => (int) ($customers[$name] ?? 0),
            ];
        });
    }

    /** Approved cooks of one zone; $which = all | pin | nopin | pending. */
    public function kitchens(int $key, string $which): Collection
    {
        $name = array_keys(self::GROUPS)[$key] ?? null;
        if ($name === null) {
            return collect();
        }
        $ids = Area::query()->whereIn('name', self::GROUPS[$name])->pluck('id');

        return Kitchen::query()->whereIn('area_id', $ids)
            ->where('status', $which === 'pending' ? KitchenStatus::Pending : KitchenStatus::Approved)
            ->when($which === 'pin', fn ($q) => $q->whereNotNull('location'))
            ->when($which === 'nopin', fn ($q) => $q->whereNull('location'))
            ->with(['user:id,name,phone', 'area:id,name'])
            ->orderBy('area_id')->orderBy('id')
            ->get();
    }
}
