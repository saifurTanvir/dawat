<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishPost;
use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\PostForCookRequest;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Services\Images\UnreadablePhotoException;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Services\Posts\PlateSplit;
use App\Services\Posts\PostForCook;
use App\Support\ContactHours;
use App\Support\DhakaTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
                        'kitchen_id' => $kitchen->id,
                        'meal_slot' => $slot->value,
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
            // F44: today's posts from my cooks, and where the plates went.
            'posts' => $this->todayPosts($me, $pairings->pluck('kitchen_id')->merge($orders->pluck('kitchen_id'))->unique()->values()),
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

    /** POST /api/v1/shahidar/kitchens/{kitchen}/posts — F43: today's food for a cook who phoned it in. */
    public function postFor(PostForCookRequest $request, Kitchen $kitchen, PostForCook $posts): JsonResponse
    {
        $me = $request->user();
        abort_unless(self::worksWith($me, $kitchen), 404);

        try {
            $post = $posts->post($kitchen, $request->validated(), $request->file('images', []), 'shahidar', $me);
        } catch (UnreadablePhotoException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['images' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'data' => ['id' => $post->id, 'title' => $post->title, 'plates_left' => $post->plates_left],
            'message' => 'পোস্ট হয়ে গেছে, গৃহিণীকে জানানো হয়েছে। '.PlateSplit::resultLine(app(PlateSplit::class)->forPost($post->load('orders'))),
        ], 201);
    }

    /** GET /api/v1/shahidar/kitchens/{kitchen}/preview — F44: where the plates will go before posting. */
    public function preview(Request $request, Kitchen $kitchen, PlateSplit $split): JsonResponse
    {
        abort_unless($request->user()->isShahidar() && self::worksWith($request->user(), $kitchen), 404);
        $data = $request->validate([
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'plates' => ['required', 'integer', 'min:0', 'max:20'],
            'price_taka' => ['nullable', 'integer', 'min:0', 'max:2000'],
        ]);

        return response()->json(['data' => $split->preview(
            $kitchen, MealSlot::from($data['meal_slot']), (int) $data['plates'],
            isset($data['price_taka']) ? (int) $data['price_taka'] * 100 : null,
        )]);
    }

    /** Cooks a shahidar may post for: theirs, paired with their customers, or in their areas. */
    public static function worksWith(User $me, Kitchen $kitchen): bool
    {
        return $kitchen->shahidar_id === $me->id
            || Pairing::open()->where('kitchen_id', $kitchen->id)->where('shahidar_id', $me->id)->exists()
            || ($kitchen->area_id && $me->shahidarAreas()->whereKey($kitchen->area_id)->exists());
    }

    /**
     * F44: today's posts from the cooks this shahidar works with, and where
     * their plates went (with names: shahidars may know who).
     */
    private function todayPosts(User $me, Collection $kitchenIds): array
    {
        $split = app(PlateSplit::class);

        return Post::whereIn('kitchen_id', $kitchenIds)
            ->where('published_at', '>=', now(DhakaTime::TZ)->startOfDay())
            ->with(['kitchen', 'orders', 'postedBy'])->latest('published_at')->get()
            ->map(function (Post $p) use ($split, $me) {
                $s = $split->forPost($p);
                $people = $split->people($p);

                return [
                    'id' => $p->id,
                    'kitchen' => $p->kitchen->display_name,
                    'kitchen_id' => $p->kitchen_id,
                    'meal_slot' => $p->meal_slot->value,
                    'meal_slot_label' => $p->meal_slot->label(),
                    'title' => $p->title,
                    'published_at' => $p->published_at?->toIso8601String(),
                    'source_label' => PublishPost::SOURCES[$p->source ?? 'cook'] ?? '',
                    'posted_by' => $p->source === 'cook' ? null : $p->postedBy?->name,
                    'split' => $s,
                    'split_line' => PlateSplit::line($s),
                    'mine' => $people->where('shahidar_id', $me->id)->count(),
                    'people' => $people->map(fn ($r) => collect($r)->except('shahidar_id')->all())->values(),
                ];
            })->values()->all();
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
