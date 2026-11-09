<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostView;
use App\Support\Geo\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F35: counts who saw which post. One row per viewer per post per day.
 * Guests are a salted hash of IP and browser; only the viewer's nearest
 * area and a distance rounded to 100 m are kept, never coordinates.
 */
class PostViews
{
    public function __construct(private readonly PostReach $reach) {}

    /** @param  iterable<Post>  $posts */
    public function record(iterable $posts, Request $request, ?GeoPoint $origin, string $source): void
    {
        try {
            $user = $request->user('sanctum');
            $key = sha1($user ? 'user:'.$user->id : 'guest:'.config('app.key').'|'.$request->ip().'|'.$request->userAgent());
            $areaId = $origin ? $this->reach->nearestArea($origin)?->id : null;
            $today = now()->toDateString();

            $rows = [];
            foreach ($posts as $post) {
                // A cook looking at her own post is not an audience.
                if ($user && $post->kitchen?->user_id === $user->id) {
                    continue;
                }
                $distance = $origin && $post->location ? $post->location->distanceTo($origin) : null;
                $rows[] = [
                    'post_id' => $post->id,
                    'user_id' => $user?->id,
                    'viewer_key' => $key,
                    'area_id' => $areaId,
                    'distance_m' => $distance === null ? null : (int) (round($distance / 100) * 100),
                    'source' => $source,
                    'viewed_on' => $today,
                    'created_at' => now(),
                ];
            }

            if ($rows) {
                PostView::insertOrIgnore($rows);
            }
        } catch (Throwable $e) {
            // Counting must never break the feed.
            Log::warning('post view not recorded', ['error' => $e->getMessage()]);
        }
    }
}
