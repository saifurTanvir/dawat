<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Geo\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    /**
     * GET /api/v1/geocode?q= — F62: an address, a map link or "lat, lng" → places.
     * Only for signed-in people setting their own home pin; nothing is stored.
     */
    public function __invoke(Request $request, Geocoder $geocoder): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:500']])['q'];
        $results = $geocoder->search($q);

        return response()->json([
            'data' => $results,
            'message' => $results ? null : 'এই ঠিকানা খুঁজে পাওয়া যায়নি। কাছের বড় জায়গার নাম লিখুন, বা ম্যাপে পিন সরান।',
        ]);
    }
}
