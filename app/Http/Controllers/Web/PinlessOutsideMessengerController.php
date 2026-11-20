<?php

namespace App\Http\Controllers\Web;

use App\Enums\KitchenStatus;
use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Support\PinReach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admins only: approved cooks without a home pin who cannot be reached on Messenger
 * (no chat, or older than Meta's 7 days), each with her own pin link — so the
 * WhatsApp reminder pass can send it. Read in the admin's own browser session.
 */
class PinlessOutsideMessengerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $kitchens = Kitchen::query()->where('status', KitchenStatus::Approved)->whereNull('location')
            ->with(['user:id,name,phone', 'area:id,name'])->orderBy('id')->get();
        $reach = PinReach::of($kitchens);

        return response()->json(['data' => $kitchens
            ->filter(fn (Kitchen $k) => ($reach[$k->id]['reach'] ?? 'none') !== 'open' && filled($k->user?->phone))
            ->map(fn (Kitchen $k) => [
                'id' => $k->id, 'name' => $k->user->name, 'kitchen' => $k->display_name, 'phone' => $k->user->phone,
                'area' => $k->area?->name, 'reach' => $reach[$k->id]['reach'], 'link' => PinLink::forKitchen($k, $request->user())->url(),
            ])->values()]);
    }
}
