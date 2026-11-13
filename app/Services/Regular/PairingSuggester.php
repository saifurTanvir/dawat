<?php

namespace App\Services\Regular;

use App\Enums\KitchenStatus;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;

/**
 * F55: which cook and shahidar to pair a regular customer with.
 *
 * A cook is "free" when she is approved and has no regular customer (one cook,
 * one customer). Free cooks are ranked nearest first, from the customer's home
 * pin, or from the middle of their area when there is no pin. Admin-only:
 * distances are shown rounded and no coordinates leave the server.
 */
class PairingSuggester
{
    /** Where distances are measured from, and whether it is only the area's middle. */
    public function origin(Customer $customer): array
    {
        if ($customer->location) {
            return [$customer->location, false];
        }

        return [$customer->area?->center, true];
    }

    /**
     * Free cooks for this customer, nearest first. The customer's own cook counts as free for them.
     *
     * @return Collection<int, array{kitchen: Kitchen, distance_m: ?int}>
     */
    public function freeCooks(Customer $customer): Collection
    {
        [$from] = $this->origin($customer);

        $currentId = $customer->pairing?->kitchen_id;

        return Kitchen::query()
            ->where('status', KitchenStatus::Approved)
            // Their current cook stays listed even if she has others from before F55.
            ->where(fn ($query) => $query
                ->whereDoesntHave('pairings', fn ($query) => $query->where('customer_id', '!=', $customer->id))
                ->when($currentId, fn ($query) => $query->orWhereKey($currentId)))
            ->with('area')
            ->get()
            ->map(fn (Kitchen $kitchen) => [
                'kitchen' => $kitchen,
                'distance_m' => $from && $kitchen->location ? (int) round($kitchen->location->distanceTo($from)) : null,
            ])
            // Nearest first; cooks without a pin go last, same-area ones before others.
            ->sortBy(fn (array $row) => [
                $row['distance_m'] === null ? 1 : 0,
                $row['distance_m'] ?? ($row['kitchen']->area_id === $customer->area_id ? 0 : 1),
                $row['kitchen']->display_name,
            ])
            ->values();
    }

    /** @return array{kitchen: Kitchen, distance_m: ?int}|null */
    public function suggestCook(Customer $customer): ?array
    {
        return $this->freeCooks($customer)->first();
    }

    /** Shahidars working in the customer's area, fewest regular customers first. */
    public function shahidars(Customer $customer): Collection
    {
        return User::role(Roles::SHAHIDAR)
            ->whereHas('shahidarAreas', fn ($query) => $query->whereKey($customer->area_id))
            ->withCount(['shahidarPairings as load' => fn ($query) => $query->whereNull('ended_at')])
            ->orderBy('load')->orderBy('name')
            ->get();
    }

    public function suggestShahidar(Customer $customer): ?User
    {
        return $this->shahidars($customer)->first();
    }

    /** "প্রায় ৮০০ মিটার" / "প্রায় ২.৩ কিমি", rounded so it never pinpoints a home. */
    public static function distanceText(?int $metres): string
    {
        if ($metres === null) {
            return 'দূরত্ব জানা নেই';
        }
        $text = $metres < 1000
            ? max(100, (int) (round($metres / 100) * 100)).' মিটার'
            : rtrim(rtrim(number_format(round($metres / 1000, 1), 1), '0'), '.').' কিমি';

        return 'প্রায় '.Order::banglaDigits($text);
    }

    /** The line shown above the cook picker. */
    public function suggestionLine(Customer $customer): string
    {
        $cook = $this->suggestCook($customer);
        if (! $cook) {
            return 'এখন কোনো খালি গৃহিণী নেই — সব অনুমোদিত গৃহিণী কোনো না কোনো গ্রাহকের সাথে জোড়া।';
        }
        [, $approx] = $this->origin($customer);
        $line = 'প্ল্যাটফর্মের সাজেশন: '.$cook['kitchen']->display_name.' ('.($cook['kitchen']->area?->name ?? 'এলাকা নেই').'), '.self::distanceText($cook['distance_m']).' দূরে';
        if ($approx) {
            $line .= ' — গ্রাহকের বাসার পিন নেই, তাই এলাকার মাঝখান থেকে আন্দাজ';
        }

        return $line.'।';
    }

    /** F55: cooks who still have more than one regular customer from before the rule, id => count. */
    public static function overloadedCookIds(): Collection
    {
        return Pairing::query()->whereNull('ended_at')
            ->selectRaw('kitchen_id, COUNT(*) as n')->groupBy('kitchen_id')->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'kitchen_id');
    }
}
