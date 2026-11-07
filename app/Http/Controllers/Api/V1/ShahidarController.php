<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Pairing;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F27: a shahidar's day. Only their own customers' boxes; they see the cook's
 * pickup address and the customer's delivery address because it is their job.
 */
class ShahidarController extends Controller
{
    public function __construct(private readonly BookingPlanner $planner, private readonly Delivery $delivery) {}

    /** GET /api/v1/shahidar/today */
    public function today(Request $request): JsonResponse
    {
        $me = $request->user();
        $orders = Order::where('shahidar_id', $me->id)
            ->whereDate('service_date', BookingPlanner::today())
            ->with(['customer.user', 'kitchen.user', 'kitchen.area', 'sticker'])
            ->orderBy('seq')
            ->get();

        // Customers who should get food today but whose cook has not posted yet.
        $waiting = [];
        $pairings = Pairing::open()->where('shahidar_id', $me->id)->with(['kitchen', 'customer.user'])->get();
        foreach ($pairings->groupBy('kitchen_id') as $group) {
            $kitchen = $group->first()->kitchen;
            foreach (MealSlot::cases() as $slot) {
                foreach ($this->planner->expected($kitchen, $slot)->where('shahidar_id', $me->id) as $p) {
                    $waiting[] = [
                        'kitchen' => $kitchen->display_name,
                        'kitchen_phone' => $kitchen->user->phone,
                        'customer' => $p->customer->user->name,
                        'meal_slot_label' => $slot->label(),
                        'plates' => $p->customer->plates,
                    ];
                }
            }
        }

        return response()->json(['data' => [
            'date' => BookingPlanner::today(),
            'orders' => $orders->map(fn (Order $o) => $this->row($o))->values(),
            'waiting' => $waiting,
        ]]);
    }

    public function pickup(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);
        $data = $request->validate(['sticker' => ['nullable', 'string', 'max:200']]);

        return $this->done($this->delivery->pickup($order, $request->user(), $data['sticker'] ?? null), 'পিকআপ হয়েছে।');
    }

    public function deliver(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);
        $data = $request->validate(['code' => ['nullable', 'digits:4']], ['code.digits' => 'কোড ৪ অঙ্কের।']);

        return $this->done($this->delivery->deliverByShahidar($order, $request->user(), $data['code'] ?? null), 'পৌঁছে দেওয়া হয়েছে।');
    }

    public function fail(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'কী হয়েছিল লিখুন।']);

        return $this->done($this->delivery->fail($order, $request->user(), $data['reason']), 'লিখে রাখা হয়েছে।');
    }

    /** The customer called: not today. Plates go back to the feed. */
    public function skip(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->done($this->planner->cancel($order, 'shahidar', $data['reason'] ?? 'গ্রাহক শাহিদারকে জানিয়েছেন'), 'আজকের জন্য বাদ দেওয়া হয়েছে।');
    }

    private function mine(Request $request, Order $order): void
    {
        abort_unless($order->shahidar_id === $request->user()->id, 404);
    }

    private function done(Order $order, string $message): JsonResponse
    {
        $order->load(['customer.user', 'kitchen.user', 'kitchen.area', 'sticker']);

        return response()->json(['data' => $this->row($order), 'message' => $message]);
    }

    private function row(Order $o): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code(),
            'meal_slot' => $o->meal_slot->value,
            'meal_slot_label' => $o->meal_slot->label(),
            'title' => $o->title,
            'plates' => $o->plates,
            'total_paisa' => $o->totalPaisa(),
            'status' => $o->status->value,
            'status_label' => $o->cancelled_by === 'cook' ? 'আজ রান্না নেই' : $o->status->label(),
            'cancel_reason' => $o->cancel_reason,
            'confirm_method' => $o->confirm_method,
            'sticker' => $o->sticker?->displayCode(),
            'kitchen' => [
                'id' => $o->kitchen_id,
                'name' => $o->kitchen->display_name,
                'phone' => $o->kitchen->user->phone,
                'address' => $o->kitchen->pickup_address ?? $o->kitchen->area?->name,
            ],
            'customer' => [
                'name' => $o->customer->user->name,
                'phone' => $o->customer->user->phone,
                'address' => $o->customer->address,
                'note' => $o->customer->note,
            ],
            'paid' => $o->paid_at !== null,
        ];
    }
}
