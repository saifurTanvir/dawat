<?php

namespace App\Services\Home;

use App\Enums\KitchenStatus;
use App\Enums\PostStatus;
use App\Models\Kitchen;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * F52: the numbers and photos on the first screen, for visitors who have not
 * picked a place yet. Area names only — never a location.
 */
class HomeSummary
{
    public const SAMPLES = 6;

    public const CACHE_SECONDS = 60;

    public function get(): array
    {
        return Cache::remember('home-summary', self::CACHE_SECONDS, fn () => $this->build());
    }

    public function build(): array
    {
        $today = Post::query()->visibleNow()
            ->whereHas('kitchen', fn ($query) => $query->where('status', KitchenStatus::Approved));

        $kitchens = Kitchen::query()->where('status', KitchenStatus::Approved);
        $rated = (clone $kitchens)->where('rating_count', '>', 0);
        $ratingCount = (int) $rated->sum('rating_count');
        $ratingAvg = $ratingCount
            ? round((float) (clone $rated)->selectRaw('SUM(rating_avg * rating_count) as s')->value('s') / $ratingCount, 1)
            : null;

        return [
            'today' => [
                'count' => (clone $today)->count(),
                'min_price_paisa' => (clone $today)->min('price_paisa'),
            ],
            'kitchens' => (clone $kitchens)->count(),
            'rating' => ['avg' => $ratingAvg, 'count' => $ratingCount],
            'samples' => $this->samples($today),
            // F53: the shahidar door's promise
            'shahidar_income' => config('dawat.shahidar.income'),
        ];
    }

    /** Today's dishes with a photo; filled up with recent ones so the strip is never empty. */
    private function samples($today): array
    {
        $with = ['images', 'area'];
        $live = (clone $today)->whereHas('images')->with($with)
            ->latest('published_at')->limit(self::SAMPLES)->get();

        $earlier = collect();
        if ($live->count() < 3) {
            $earlier = Post::query()
                ->whereIn('status', [PostStatus::Live, PostStatus::SoldOut, PostStatus::Expired])
                ->whereNotIn('id', $live->pluck('id'))
                ->whereHas('images')
                ->whereHas('kitchen', fn ($query) => $query->where('status', KitchenStatus::Approved))
                ->with($with)->latest('published_at')->limit(self::SAMPLES - $live->count())->get();
        }

        $row = fn (Post $post, bool $isToday) => [
            'id' => $isToday ? $post->id : null,
            'title' => $post->title,
            'price_paisa' => $post->price_paisa,
            'meal_slot_label' => $post->meal_slot->label(),
            'area' => $post->area?->name,
            'image_url' => $post->images->first()?->url(),
            'today' => $isToday,
            'plates_left' => $isToday ? $post->plates_left : null,
        ];

        return $live->map(fn ($p) => $row($p, true))
            ->concat($earlier->map(fn ($p) => $row($p, false)))
            ->values()->all();
    }
}
