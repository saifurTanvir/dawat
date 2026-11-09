<?php

namespace App\Services\Feed;

use App\Models\Area;
use App\Models\Order;
use App\Models\Post;
use App\Support\Geo\GeoPoint;
use Illuminate\Support\Collection;

/**
 * F35: where a post shows and where a buyer is.
 *
 * Normally a buyer sees posts within the distance they pick (up to
 * "পুরো এলাকা", 8 km). The admin can widen a post: everyone within reach_m
 * sees it whatever they picked, and buyers inside extra areas see it too.
 */
class PostReach
{
    /** Choices offered in the admin panel, metres => label. */
    public const REACH_OPTIONS = [
        1000 => '১ কিমির মধ্যে সবাই',
        2000 => '২ কিমির মধ্যে সবাই',
        3000 => '৩ কিমির মধ্যে সবাই',
        5000 => '৫ কিমির মধ্যে সবাই',
        8000 => '৮ কিমির মধ্যে সবাই',
        12000 => '১২ কিমির মধ্যে সবাই',
    ];

    /** @var Collection<int, Area>|null */
    private ?Collection $areas = null;

    /** @return Collection<int, Area> */
    public function areas(): Collection
    {
        return $this->areas ??= Area::active()->orderBy('sort_order')->get();
    }

    /** Active areas whose circle holds the point. @return list<int> */
    public function areaIdsContaining(GeoPoint $point): array
    {
        return $this->areas()
            ->filter(fn (Area $a) => $a->center && $a->center->distanceTo($point) <= $a->radius_m)
            ->pluck('id')->values()->all();
    }

    /** The active area whose centre is closest, for "আপনি আছেন উত্তর বাড্ডার কাছে". */
    public function nearestArea(GeoPoint $point): ?Area
    {
        return $this->areas()
            ->filter(fn (Area $a) => $a->center !== null)
            ->sortBy(fn (Area $a) => $a->center->distanceTo($point))
            ->first();
    }

    /**
     * For the admin: every active area and how this post reaches it.
     *
     * @return list<array{area: Area, distance_m: int, how: string, always: bool}>
     */
    public function coverage(Post $post): array
    {
        $extra = $post->reachAreas->pluck('id')->all();
        $rows = [];

        foreach ($this->areas() as $area) {
            if (! $area->center || ! $post->location) {
                continue;
            }
            $d = (int) round($post->location->distanceTo($area->center));
            $always = in_array($area->id, $extra, true) || ($post->reach_m && $d <= $post->reach_m);

            $how = match (true) {
                in_array($area->id, $extra, true) => 'অ্যাডমিন যোগ করেছেন: পুরো এলাকা দেখবে',
                $post->reach_m && $d <= $post->reach_m => 'বাড়ানো পরিধির ভেতরে: সবাই দেখবে',
                $d <= FeedRadius::OneKm->metres() => '১ কিমি বা বেশি বাছলেই দেখবে',
                $d <= FeedRadius::ThreeKm->metres() => '৩ কিমি বা "পুরো এলাকা" বাছলে দেখবে',
                $d <= FeedRadius::Area->metres() => 'শুধু "পুরো এলাকা" বাছলে দেখবে',
                default => 'দেখবে না',
            };

            $rows[] = ['area' => $area, 'distance_m' => $d, 'how' => $how, 'always' => $always];
        }

        usort($rows, fn ($a, $b) => $a['distance_m'] <=> $b['distance_m']);

        return $rows;
    }

    /** "স্বাভাবিক" or "৫ কিমি + ২টা এলাকা", for tables. */
    public static function summary(?int $reachM, int $extraAreas): string
    {
        $parts = [];
        if ($reachM) {
            $parts[] = self::km($reachM);
        }
        if ($extraAreas > 0) {
            $parts[] = Order::banglaDigits($extraAreas).'টা এলাকা';
        }

        return $parts ? implode(' + ', $parts) : 'স্বাভাবিক';
    }

    public static function km(int $metres): string
    {
        $km = $metres / 1000;

        return Order::banglaDigits(rtrim(rtrim(number_format($km, 1), '0'), '.')).' কিমি';
    }
}
