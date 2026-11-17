<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PinLink;
use App\Services\Kitchens\PinLinks;
use App\Support\Geo\Geocoder;
use App\Support\Geo\GeoPoint;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F68: the cook's "drop your home pin" page, from a link; no login. */
class PinController extends Controller
{
    public function show(string $token): View
    {
        $link = PinLink::where('token', $token)->with(['kitchen.user', 'kitchen.area'])->firstOrFail();

        return view('pins.show', [
            'link' => $link,
            // Only whether she already sent one — never the pin itself on an open link.
            'state' => $link->isExpired() ? 'expired' : ($link->submitted_at ? ($link->review === null && $link->flagged ? 'review' : 'done') : 'new'),
            'meta' => Seo::meta('বাসার লোকেশন দিন — দাওয়াত', 'দাওয়াত', null, 'noindex,nofollow') + ['canonical' => null],
        ]);
    }

    public function store(Request $request, string $token, PinLinks $pins): JsonResponse
    {
        $link = PinLink::where('token', $token)->firstOrFail();
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:20,27'],
            'lng' => ['required', 'numeric', 'between:88,93'],
            'mode' => ['required', 'in:home,map'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $pins->submit($link, GeoPoint::make($data['lat'], $data['lng']), $data['mode'], isset($data['accuracy']) ? (int) round($data['accuracy']) : null);

        return response()->json(['result' => $result]);
    }

    public function search(Request $request, string $token, Geocoder $geocoder): JsonResponse
    {
        $link = PinLink::where('token', $token)->firstOrFail();
        abort_if($link->isExpired(), 410);
        $q = (string) $request->validate(['q' => ['required', 'string', 'min:3', 'max:300']])['q'];

        return response()->json(['data' => $geocoder->search($q)]);
    }
}
