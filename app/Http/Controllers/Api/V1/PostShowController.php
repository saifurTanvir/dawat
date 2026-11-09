<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FeedPostResource;
use App\Models\Post;
use App\Services\Feed\PostViews;
use App\Support\Geo\Bangladesh;
use App\Support\Geo\GeoPoint;
use Illuminate\Http\Request;

class PostShowController extends Controller
{
    /**
     * GET /api/v1/posts/{post}[?lat=&lng=] — one post, e.g. from a shared link.
     * Sold-out or finished posts still open (marked unavailable); hidden ones do not.
     */
    public function __invoke(Request $request, Post $post, PostViews $views): FeedPostResource
    {
        abort_if($post->status === PostStatus::Hidden || ! $post->kitchen->isApproved(), 404, 'এই পোস্টটি আর দেখা যাচ্ছে না।');

        $near = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:'.Bangladesh::MIN_LAT.','.Bangladesh::MAX_LAT],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG],
        ]);

        $post->load(['images', 'tags', 'kitchen.area'])
            ->loadCount(['comments as comments_count' => fn ($c) => $c->where('status', 'visible')]);

        $origin = isset($near['lat'], $near['lng']) ? GeoPoint::make($near['lat'], $near['lng']) : null;
        if ($origin) {
            $post->distance_m = $post->location->distanceTo($origin);
        }
        $views->record([$post], $request, $origin, 'detail');

        return FeedPostResource::make($post)->additional([
            'meta' => ['is_available' => Post::visibleNow()->whereKey($post->id)->exists()],
        ]);
    }
}
