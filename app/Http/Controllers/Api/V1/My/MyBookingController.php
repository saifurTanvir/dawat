<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Pairing;
use App\Services\Kitchens\ActForCook;
use App\Services\Kitchens\CookActions;
use App\Services\Orders\BookingPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * F26: the cook's regular customers today, per meal. Customers appear by first
 * name only; their phone and address are for the shahidar.
 */
class MyBookingController extends Controller
{
    public function __construct(private readonly BookingPlanner $planner) {}

    /** GET /api/v1/my/bookings */
    public function index(Request $request): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');

        $orders = Order::where('kitchen_id', $kitchen->id)
            ->whereDate('service_date', BookingPlanner::today())
            ->with(['customer.user', 'buyer'])
            ->get()
            ->groupBy(fn (Order $o) => $o->meal_slot->value);

        $slots = collect(MealSlot::cases())->map(function (MealSlot $slot) use ($kitchen, $orders) {
            $waiting = $this->planner->expected($kitchen, $slot);
            $done = $orders->get($slot->value, collect());
            if ($waiting->isEmpty() && $done->isEmpty()) {
                return null;
            }

            // F38: boxes a shahidar will collect, and whether the cook said "ready".
            $collect = $done->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Booked, OrderStatus::Asking], true) && ! $o->isSelfPickup() && $o->shahidar_id);
            $ready = $done->whereNotNull('ready_at')->sortByDesc('ready_at')->first();

            return [
                'meal_slot' => $slot->value,
                'label' => $slot->label(),
                'can_ready' => $collect->isNotEmpty(),
                'ready_at' => $ready?->ready_at?->toIso8601String(),
                'ready_note' => $ready?->ready_note,
                // Still to be booked: they get plates from the next post for this meal.
                'waiting_plates' => (int) $waiting->sum(fn (Pairing $p) => $p->customer->plates),
                // F30: the lowest price limit among them; above it they are asked first.
                'price_limit_paisa' => $waiting->map(fn (Pairing $p) => $p->customer->max_price_paisa)->filter()->min(),
                'limited_customers' => $waiting->filter(fn (Pairing $p) => $p->customer->max_price_paisa !== null)->count(),
                // F44: the cook sees how many people, never who.
                'waiting_people' => $waiting->count(),
                'orders' => $done->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code(),
                    'name' => $o->isOneOff() ? self::firstName($o->recipient()->name) : 'নিয়মিত গ্রাহক',
                    // F29: one-off feed orders; for self pickup the cook calls the buyer.
                    'kind' => $o->kind,
                    'receive' => $o->receive,
                    'phone' => $o->isSelfPickup() && $o->status === OrderStatus::Booked ? $o->recipient()->phone : null,
                    'total_paisa' => $o->totalPaisa(),
                    'can_decline' => $o->isOneOff() && $o->status === OrderStatus::Booked && $o->created_at->gt(now()->subMinutes(self::DECLINE_MINUTES)),
                    'can_hand' => $o->isSelfPickup() && $o->status === OrderStatus::Booked,
                    'plates' => $o->plates,
                    'status' => $o->status->value,
                    'status_label' => $o->status === OrderStatus::Cancelled && $o->cancelled_by === 'cook' ? 'আজ রান্না নেই' : $o->status->label(),
                ])->values(),
            ];
        })->filter()->values();

        return response()->json([
            'data' => [
                'regular_customers' => $kitchen->pairings()->count(),
                'slots' => $slots,
                // F46: what the team or a shahidar did for her today.
                'acted_for' => ActForCook::today($kitchen),
            ],
        ]);
    }

    public const DECLINE_MINUTES = CookActions::DECLINE_MINUTES;

    /** POST /api/v1/my/bookings/ready — F38: "food is ready", with where/when to collect it. */
    public function ready(Request $request, CookActions $actions): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404);
        $data = $request->validate([
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $n = $actions->ready($kitchen, MealSlot::from($data['meal_slot']), $data['note'] ?? null);

        return response()->json(['message' => 'শাহিদারকে জানানো হয়েছে ('.Order::banglaDigits($n).'টা বক্স)।']);
    }

    /** POST /api/v1/my/orders/{order}/decline — F29: can't give this one-off order. */
    public function decline(Request $request, Order $order, CookActions $actions): JsonResponse
    {
        $this->own($request, $order);
        $actions->decline($order);

        return response()->json(['message' => 'বাতিল হয়েছে, ক্রেতাকে জানানো হচ্ছে।']);
    }

    /** POST /api/v1/my/orders/{order}/handed — F29: the buyer collected it and paid the cook. */
    public function handed(Request $request, Order $order, CookActions $actions): JsonResponse
    {
        $this->own($request, $order);
        $actions->handed($order, $request->user());

        return response()->json(['message' => 'দেওয়া হয়েছে, লিখে রাখা হলো।']);
    }

    private function own(Request $request, Order $order): void
    {
        abort_unless($order->kitchen_id === $request->user()->kitchen?->id, 404);
    }

    /** POST /api/v1/my/bookings/off — "আজ রান্না নেই" for one meal. */
    public function off(Request $request): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404);
        $data = $request->validate(['meal_slot' => ['required', Rule::enum(MealSlot::class)]]);

        $orders = $this->planner->cookOff($kitchen, MealSlot::from($data['meal_slot']));

        return response()->json([
            'message' => $orders->isEmpty()
                ? 'এই বেলায় আজ কারো বুকিং বাকি নেই।'
                : Order::banglaDigits($orders->count()).' জন গ্রাহককে জানানো হচ্ছে। শাহিদার বিকল্প দেখবেন।',
        ]);
    }

    private static function firstName(?string $name): string
    {
        return $name ? Str::before(trim($name), ' ') : 'গ্রাহক';
    }
}
