<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Kitchen;
use App\Services\Admin\Impersonate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F51: open the app as another person, and come back. */
class ImpersonationController extends Controller
{
    /** POST /api/v1/impersonate/claim — the one-time code from the link. */
    public function claim(Request $request, Impersonate $impersonate): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:40']]);
        $result = $impersonate->claim($data['code']);
        if (! $result) {
            return response()->json(['message' => 'এই লিংক আর কাজ করছে না। আবার "অ্যাপ দেখুন" চাপুন।'], 422);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => MeResource::make($result['impersonation']->target),
        ]);
    }

    /** POST /api/v1/impersonate/end — back to your own account. */
    public function end(Request $request, Impersonate $impersonate): JsonResponse
    {
        $imp = Impersonate::current($request->user());
        abort_unless($imp, 404);
        $impersonate->end($imp);

        return response()->json(['message' => 'নিজের অ্যাকাউন্টে ফিরে গেছেন।']);
    }

    /** POST /api/v1/shahidar/kitchens/{kitchen}/view-as — a shahidar opens their cook's app. */
    public function shahidarStart(Request $request, Kitchen $kitchen, Impersonate $impersonate): JsonResponse
    {
        abort_unless($request->user()->isShahidar() && ShahidarController::worksWith($request->user(), $kitchen), 404);

        return response()->json(['url' => $impersonate->start($request->user(), $kitchen->user)]);
    }
}
