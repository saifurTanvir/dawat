<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Pairing;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Support\ContactHours;
use App\Support\DhakaTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F27: a shahidar's day. Only their own customers' boxes; they see the cook's
 * pickup address and the customer's delivery address because it is their job.
 */
class ShahidarController extends Controller
{
    public function __construct(
        private readonly BookingPlanner $planner,
        private readonly Delivery $delivery,
        private readonly Settlements $money,
    ) {}

    /** GET /api/v1/shahidar/today */
    public function today(Request $request): JsonResponse
    {
        $me = $request->user();
        $orders = Order::where('shahidar_id', $me->id)
            ->whereDate('service_date', BookingPlanner::today())
            ->with(['customer.user', 'buyer', 'kitchen.user', 'kitchen.area', 'sticker'])
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
                        'kitchen_contact' => ContactHours::card($kitchen->user, [$slot], 'আসসালামু আলাইকুম, দাওয়াত থেকে বলছি। আজ '.$slot->label().'র রান্নার খবর জানতে চাই।'),
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
            // F28: cash collected and not yet handed to Dawat.
            'cash_held_paisa' => $this->money->cashHeldBy($me),
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
        $data = $request->validate([
            'code' => ['nullable', 'digits:4'],
            'cash_received' => ['sometimes', 'boolean'],
        ], ['code.digits' => 'কোড ৪ অঙ্কের।']);

        $order = $this->delivery->deliverByShahidar($order, $request->user(), $data['code'] ?? null);
        // F39: weekly/monthly customers pay on their bill, not at the door.
        if ($request->boolean('cash_received', true) && ! $order->customer?->paysByBill()) {
            $order = $this->money->markPaid($order, $request->user());
        }

        return $this->done($order, 'পৌঁছে দেওয়া হয়েছে।');
    }

    /** POST — cash taken later, e.g. after the customer scanned the box. */
    public function paid(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);

        return $this->done($this->money->markPaid($order, $request->user()), 'টাকা পাওয়া লিখে রাখা হলো।');
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

    /** The customer said yes on the phone to today's higher price (F30). */
    public function accept(Request $request, Order $order): JsonResponse
    {
        $this->mine($request, $order);

        return $this->done($this->planner->accept($order), 'গ্রাহক নেবেন, বুক হয়েছে।');
    }

    private function mine(Request $request, Order $order): void
    {
        abort_unless($order->shahidar_id === $request->user()->id, 404);
    }

    private function done(Order $order, string $message): JsonResponse
    {
        $order->load(['customer.user', 'buyer', 'kitchen.user', 'kitchen.area', 'sticker']);

        return response()->json(['data' => $this->row($order), 'message' => $message]);
    }

    private function row(Order $o): array
    {
        // On the day of the box, its meal's delivery window is open whatever hours they chose.
        $todaySlots = $o->service_date?->isSameDay(now(DhakaTime::TZ)) ? [$o->meal_slot] : [];

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
            'ask_until' => $o->ask_until?->toIso8601String(),
            'price_paisa' => intdiv($o->food_paisa, max(1, $o->plates)),
            'max_price_paisa' => $o->customer?->max_price_paisa,
            // F29: one-off feed orders are marked "একবারের".
            'kind' => $o->kind,
            'confirm_method' => $o->confirm_method,
            'sticker' => $o->sticker?->displayCode(),
            'kitchen' => [
                'id' => $o->kitchen_id,
                'name' => $o->kitchen->display_name,
                'phone' => $o->kitchen->user->phone,
                'address' => $o->kitchen->pickup_address ?? $o->kitchen->area?->name,
                // F41: may they be called now, and a free WhatsApp link.
                'contact' => ContactHours::card($o->kitchen->user, $todaySlots, 'আসসালামু আলাইকুম, দাওয়াত থেকে বলছি। বক্স '.$o->code().' নিয়ে।'),
            ],
            'customer' => [
                'name' => $o->recipient()->name ?: 'ক্রেতা',
                'phone' => $o->recipient()->phone,
                'address' => $o->recipientAddress(),
                'note' => $o->recipientNote(),
                'contact' => ContactHours::card($o->recipient(), $todaySlots, 'আসসালামু আলাইকুম, দাওয়াত থেকে বলছি। আপনার বক্স '.$o->code().' নিয়ে।'),
            ],
            'paid' => $o->paid_at !== null,
            'pays_by_bill' => (bool) $o->customer?->paysByBill(),
            // F38: the cook's "ready" and where/when to collect; new boxes are highlighted.
            'ready_at' => $o->ready_at?->toIso8601String(),
            'ready_note' => $o->ready_note,
            'created_at' => $o->created_at?->toIso8601String(),
        ];
    }
}
