<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Post;
use App\Services\Orders\BookingPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F29: a buyer's one-off orders from the feed ("নিতে চাই"). */
class OrderController extends Controller
{
    /** POST /api/v1/posts/{post}/orders */
    public function store(Request $request, Post $post, PlaceOrder $place): JsonResponse
    {
        $data = $request->validate([
            'plates' => ['required', 'integer', 'between:1,'.PlaceOrder::MAX_PLATES],
            'receive' => ['required', Rule::in(['delivery', 'self'])],
            'address' => ['required_if:receive,delivery', 'nullable', 'string', 'min:5', 'max:255'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'plates.between' => 'এক অর্ডারে ১ থেকে ৫ প্লেট।',
            'address.required_if' => 'কোথায় পৌঁছাবে, ঠিকানা লিখুন।',
            'address.min' => 'ঠিকানাটা আরেকটু বিস্তারিত লিখুন।',
        ]);

        $order = $place($post, $request->user(), $data['plates'], $data['receive'], $data['address'] ?? null, $data['note'] ?? null);

        return response()->json([
            'data' => $this->row($order->load(['kitchen', 'shahidar'])),
            'message' => $order->isSelfPickup()
                ? 'বুক হয়েছে। গৃহিণী ফোন করে জানাবেন কোথায় নেবেন।'
                : 'বুক হয়েছে। শাহিদার পৌঁছে দেবেন; ডেলিভারি কোড SMS-এ আসছে।',
        ], 201);
    }

    /** GET /api/v1/orders — this week's one-off orders and a remembered address. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::where('buyer_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['kitchen', 'shahidar'])
            ->latest('id')->get();

        $lastAddress = Order::where('buyer_id', $user->id)->whereNotNull('delivery_address')->latest('id')->value('delivery_address')
            ?? Customer::where('user_id', $user->id)->value('address');

        return response()->json([
            'data' => $orders->map(fn (Order $o) => $this->row($o))->values(),
            'last_address' => $lastAddress,
            'can_order' => $user->canOrder(),
        ]);
    }

    /** POST /api/v1/orders/{order}/cancel — before pickup. */
    public function cancel(Request $request, Order $order, BookingPlanner $planner): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $planner->cancel($order, 'buyer', 'ক্রেতা বাতিল করেছেন');

        return response()->json(['data' => $this->row($order->fresh(['kitchen', 'shahidar'])), 'message' => 'অর্ডার বাতিল হয়েছে।']);
    }

    private function row(Order $o): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code(),
            'date' => $o->service_date->toDateString(),
            'meal_slot_label' => $o->meal_slot->label(),
            'title' => $o->title,
            'kitchen' => $o->kitchen->display_name,
            'plates' => $o->plates,
            'total_paisa' => $o->totalPaisa(),
            'receive' => $o->receive,
            'address' => $o->delivery_address,
            'status' => $o->status->value,
            'status_label' => match (true) {
                $o->status === OrderStatus::Cancelled && $o->cancelled_by === 'cook' => 'গৃহিণী দিতে পারেননি',
                default => $o->status->label(),
            },
            // Who to call about it: the shahidar, never the cook's number.
            'shahidar' => $o->shahidar ? ['name' => $o->shahidar->name, 'phone' => $o->shahidar->phone] : null,
            'can_cancel' => $o->status === OrderStatus::Booked,
        ];
    }
}
