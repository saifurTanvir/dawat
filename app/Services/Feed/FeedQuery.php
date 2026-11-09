<?php

namespace App\Services\Feed;

use App\Enums\MealSlot;
use App\Models\Post;
use App\Support\Geo\GeoPoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's food near a point, nearest first.
 *
 * A bounding box lets MySQL use the spatial index on posts.location; the exact
 * great-circle distance then filters and sorts. Distances leave the server
 * rounded (see FeedPostResource), never coordinates.
 */
class FeedQuery
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly GeoPoint $origin,
        private readonly FeedRadius $radius = FeedRadius::ThreeKm,
        private readonly ?MealSlot $meal = null,
        /** F35: active areas the buyer stands in; widened posts aimed there show too. */
        private readonly ?array $areaIds = null,
    ) {}

    public function paginate(int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    public function query(): Builder
    {
        $metres = $this->radius->metres();
        $point = "ST_GeomFromText(?, 4326, 'axis-order=long-lat')";
        $wkt = sprintf('POINT(%F %F)', $this->origin->lng, $this->origin->lat);

        return Post::query()
            ->visibleNow()
            ->select('posts.*')
            ->selectRaw("ST_Distance_Sphere(posts.location, {$point}) AS distance_m", [$wkt])
            ->where(function (Builder $q) use ($point, $wkt, $metres) {
                // Within the distance the buyer picked…
                $q->where(fn (Builder $near) => $near
                    ->whereRaw("MBRContains(ST_GeomFromText(?, 4326, 'axis-order=long-lat'), posts.location)", [$this->boundingBox($metres)])
                    ->whereRaw("ST_Distance_Sphere(posts.location, {$point}) <= ?", [$wkt, $metres]))
                    // …or a post the admin widened (F35): a larger radius, or the buyer's area added.
                    ->orWhere(fn (Builder $wide) => $wide->where('posts.boosted', true)
                        ->where(fn (Builder $how) => $how
                            ->whereRaw("(posts.reach_m IS NOT NULL AND ST_Distance_Sphere(posts.location, {$point}) <= posts.reach_m)", [$wkt])
                            ->when($this->areaIds(), fn (Builder $a, array $ids) => $a->orWhereHas('reachAreas', fn (Builder $r) => $r->whereIn('areas.id', $ids)))));
            })
            ->whereHas('kitchen', fn (Builder $k) => $k->where('status', 'approved')
                ->whereHas('user', fn (Builder $u) => $u->where('status', 'active')))
            ->when($this->meal, fn (Builder $q) => $q->where('meal_slot', $this->meal))
            ->with([
                'images',
                'tags',
                'kitchen:id,user_id,area_id,display_name,allow_call,allow_message,allow_comment,allow_order',
                'kitchen.area:id,delivery_fee_paisa',
            ])
            ->withCount(['comments as comments_count' => fn (Builder $c) => $c->where('status', 'visible')])
            ->orderBy('distance_m')
            ->orderByDesc('published_at');
    }

    /** @return list<int> */
    private function areaIds(): array
    {
        return $this->areaIds ?? app(PostReach::class)->areaIdsContaining($this->origin);
    }

    /** WKT polygon (long-lat order) around the origin, slightly larger than the radius. */
    private function boundingBox(int $metres): string
    {
        $dLat = ($metres * 1.05) / 111_320;
        $dLng = ($metres * 1.05) / (111_320 * max(0.2, cos(deg2rad($this->origin->lat))));

        $minLat = $this->origin->lat - $dLat;
        $maxLat = $this->origin->lat + $dLat;
        $minLng = $this->origin->lng - $dLng;
        $maxLng = $this->origin->lng + $dLng;

        return sprintf(
            'POLYGON((%1$F %3$F, %2$F %3$F, %2$F %4$F, %1$F %4$F, %1$F %3$F))',
            $minLng, $maxLng, $minLat, $maxLat,
        );
    }
}
