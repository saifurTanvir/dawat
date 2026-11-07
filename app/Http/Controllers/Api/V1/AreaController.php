<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use App\Support\Geo\Bangladesh;
use App\Support\Geo\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    /**
     * GET /api/v1/areas — active areas. With ?lat=&lng= the nearest come first,
     * so the app can pre-select a cook's or buyer's area.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $near = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:'.Bangladesh::MIN_LAT.','.Bangladesh::MAX_LAT, 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG, 'required_with:lat'],
        ]);

        $areas = Area::active()->orderBy('sort_order')->orderBy('name')->get();

        if (isset($near['lat'], $near['lng'])) {
            $here = GeoPoint::make($near['lat'], $near['lng']);
            $areas = $areas
                ->each(fn (Area $a) => $a->distance_m = $a->center?->distanceTo($here))
                ->sortBy('distance_m')
                ->values();
        }

        return AreaResource::collection($areas);
    }
}
