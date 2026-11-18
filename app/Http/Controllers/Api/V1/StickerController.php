<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Sticker;
use App\Services\Orders\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F27: what scanning a box sticker shows. A stranger learns only that the box
 * is Dawat's; the box's customer can confirm they received it.
 */
class StickerController extends Controller
{
    /** GET /api/v1/stickers/{code} — sign-in optional. */
    public function show(Request $request, string $code): JsonResponse
    {
        $sticker = $this->find($code);
        $order = $sticker?->order;
        $user = auth('sanctum')->user();

        if (! $order) {
            return response()->json(['data' => ['state' => 'blank']]);
        }

        $role = match (true) {
            $user && $order->recipient()->id === $user->id => 'customer',
            $user && $order->shahidar_id === $user->id => 'shahidar',
            default => 'other',
        };
        if ($role === 'other') {
            // F67: whoever holds the box may confirm with the SMS code; nothing about it is shown.
            return response()->json(['data' => [
                'state' => 'other',
                'can_confirm_with_code' => $order->status === OrderStatus::PickedUp && $order->code_failures < Delivery::MAX_CODE_FAILURES,
            ]]);
        }

        return response()->json(['data' => [
            'state' => $role,
            'order' => [
                'code' => $order->code(),
                'meal_slot_label' => $order->meal_slot->label(),
                'title' => $order->title,
                'plates' => $order->plates,
                'total_paisa' => $order->totalPaisa(),
                'kitchen' => $order->kitchen->display_name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'can_confirm' => $role === 'customer' && $order->status === OrderStatus::PickedUp,
            ],
        ]]);
    }

    /** POST /api/v1/stickers/{code}/received — the customer confirms. */
    public function received(Request $request, string $code, Delivery $delivery): JsonResponse
    {
        $order = $this->find($code)?->order;
        abort_unless($order && $order->recipient()->id === $request->user()->id, 404);

        $delivery->receivedByCustomer($order, $request->user());

        return response()->json(['message' => 'ধন্যবাদ! খাবার পেয়েছেন, লিখে রাখা হলো।']);
    }

    /** POST /api/v1/stickers/{code}/confirm {code} — F67: "খাবার পেয়েছি" with the SMS code, no sign-in. */
    public function confirm(Request $request, string $code, Delivery $delivery): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:4']], ['code.*' => 'SMS-এর ৪ অঙ্কের কোডটা লিখুন।']);
        $order = $this->find($code)?->order;
        abort_unless($order, 404);

        $delivery->receivedWithCode($order, $data['code']);

        return response()->json(['message' => 'ধন্যবাদ! খাবার পেয়েছেন, লিখে রাখা হলো।']);
    }

    private function find(string $code): ?Sticker
    {
        $code = Sticker::normalize($code);

        return $code ? Sticker::with(['order.customer.user', 'order.buyer', 'order.kitchen'])->where('code', $code)->first() : null;
    }
}
