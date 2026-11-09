<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\Push\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** F37: turn free phone notifications on and off for this device. */
class PushController extends Controller
{
    /** GET /api/v1/push/key — the public half of the Web Push key pair. */
    public function key(): JsonResponse
    {
        return response()->json(['enabled' => Push::enabled(), 'key' => config('services.webpush.public_key')]);
    }

    /** POST /api/v1/push/subscribe — this browser's subscription, for the signed-in person. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:60'],
        ]);

        PushSubscription::updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => $request->user()->id,
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Keep a person's device list short (old phones, reinstalls).
        $keep = PushSubscription::where('user_id', $request->user()->id)->latest('updated_at')->limit(5)->pluck('id');
        PushSubscription::where('user_id', $request->user()->id)->whereNotIn('id', $keep)->delete();

        return response()->json(['message' => 'নোটিফিকেশন চালু হয়েছে।']);
    }

    /** DELETE /api/v1/push/subscribe — stop notifications on this browser. */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint', $request->input('endpoint'))->delete();

        return response()->json(['message' => 'নোটিফিকেশন বন্ধ হয়েছে।']);
    }
}
