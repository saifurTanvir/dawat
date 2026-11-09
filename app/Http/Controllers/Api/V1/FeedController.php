<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\FeedRequest;
use App\Http\Resources\AreaResource;
use App\Http\Resources\FeedPostResource;
use App\Services\Feed\FeedQuery;
use App\Services\Feed\PostReach;
use App\Services\Feed\PostViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    /**
     * GET /api/v1/feed?lat=&lng= | ?area=mirpur-10 [&radius=walk|1km|3km|all] [&meal=lunch]
     * Public: buyers can browse without signing in (F12–F14).
     */
    public function index(FeedRequest $request, PostReach $reach, PostViews $views): AnonymousResourceCollection|JsonResponse
    {
        $origin = $request->origin();

        if (! $origin) {
            return response()->json([
                'message' => 'কাছের খাবার দেখাতে আপনার লোকেশন দিন, অথবা এলাকা বেছে নিন।',
                'errors' => ['origin' => ['লোকেশন বা এলাকা দরকার।']],
            ], 422);
        }

        $area = $request->area();
        $posts = (new FeedQuery($origin, $request->radius(), $request->meal(), $reach->areaIdsContaining($origin)))->paginate();
        $views->record($posts->items(), $request, $origin, 'feed');
        // F35: tell the buyer where we think they are (an area name, never coordinates).
        $near = $reach->nearestArea($origin);

        return FeedPostResource::collection($posts)->additional([
            'meta' => [
                'origin' => $request->filled(['lat', 'lng']) ? 'location' : 'area',
                'area' => $area ? AreaResource::make($area) : null,
                'radius' => $request->radius()->value,
                'radius_m' => $request->radius()->metres(),
                'near_area' => $near ? ['name' => $near->name, 'slug' => $near->slug] : null,
            ],
        ]);
    }
}
