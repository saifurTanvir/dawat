<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishPost;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\V1\My\MyMenuController;
use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Services\Kitchens\ActForCook;
use App\Services\Kitchens\CookActions;
use App\Services\Orders\BookingPlanner;
use App\Services\Posts\PlateSplit;
use App\Services\Posts\PostForCook;
use App\Support\ContactHours;
use App\Support\DhakaTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F46: a shahidar acts for one of their cooks — ready, no cooking today,
 * plates, the dish name, one-off orders and her daily menu. Never the price,
 * her location or her phone number.
 */
class ShahidarKitchenController extends Controller
{
    public function __construct(
        private readonly CookActions $actions,
        private readonly ActForCook $act,
        private readonly BookingPlanner $planner,
    ) {}

    /** GET /api/v1/shahidar/kitchens/{kitchen} — the cook's day. */
    public function show(Request $request, Kitchen $kitchen, PlateSplit $split): JsonResponse
    {
        $this->allow($request, $kitchen);
        $today = BookingPlanner::today();
        $posts = Post::where('kitchen_id', $kitchen->id)->where('published_at', '>=', now(DhakaTime::TZ)->startOfDay())
            ->with(['orders'])->latest('id')->get();
        $orders = Order::where('kitchen_id', $kitchen->id)->whereDate('service_date', $today)
            ->with(['customer.user', 'buyer', 'shahidar'])->orderBy('seq')->get();
        $menus = $kitchen->menus()->get();

        $slots = collect(MealSlot::cases())->map(function (MealSlot $slot) use ($kitchen, $posts, $orders, $menus, $split) {
            $slotPosts = $posts->where('meal_slot', $slot);
            $slotOrders = $orders->where('meal_slot', $slot);
            $waiting = $this->planner->expected($kitchen, $slot);
            $menu = $menus->first(fn (KitchenMenu $m) => $m->meal_slot === $slot);
            if ($slotPosts->isEmpty() && $slotOrders->isEmpty() && $waiting->isEmpty() && ! $menu) {
                return null;
            }
            $collect = $slotOrders->filter(fn (Order $o) => $o->status === OrderStatus::Booked && ! $o->isSelfPickup() && $o->shahidar_id);
            $ready = $slotOrders->whereNotNull('ready_at')->sortByDesc('ready_at')->first();

            return [
                'meal_slot' => $slot->value,
                'label' => $slot->label(),
                'off' => PostForCook::offToday($kitchen->id, $slot),
                'can_ready' => $collect->isNotEmpty(),
                'ready_at' => $ready?->ready_at?->toIso8601String(),
                'ready_note' => $ready?->ready_note,
                'waiting' => $waiting->map(fn (Pairing $p) => ['name' => $p->customer->user->name, 'plates' => $p->customer->plates])->values(),
                'menu_today' => $menu ? PostForCook::menuToday($menu) : null,
                'posts' => $slotPosts->map(function (Post $p) use ($split) {
                    $s = $split->forPost($p);

                    return [
                        'id' => $p->id, 'title' => $p->title, 'note' => $p->note, 'price_paisa' => $p->price_paisa,
                        'plates_total' => $p->plates_total, 'plates_left' => $p->plates_left,
                        'max_left' => $p->plates_total - (int) $p->orders->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Booked, OrderStatus::Asking, OrderStatus::PickedUp], true) && ! $o->isOneOff())->sum('plates'),
                        'open' => $p->available_until->isFuture() && in_array($p->status->value, ['live', 'sold_out'], true),
                        'status_label' => $p->status->label(),
                        'source_label' => PublishPost::SOURCES[$p->source ?? 'cook'] ?? '',
                        'split' => $s,
                    ];
                })->values(),
                'orders' => $slotOrders->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code(),
                    'name' => $o->recipient()?->name ?: 'ক্রেতা',
                    'kind' => $o->isOneOff() ? ($o->isSelfPickup() ? 'নিজে নেবেন' : 'ফিড থেকে') : 'নিয়মিত',
                    'plates' => $o->plates,
                    'status_label' => $o->status->label(),
                    'can_decline' => $o->isOneOff() && $o->status === OrderStatus::Booked,
                    'can_hand' => $o->isSelfPickup() && $o->status === OrderStatus::Booked,
                ])->values(),
            ];
        })->filter()->values();

        return response()->json(['data' => [
            'kitchen' => [
                'id' => $kitchen->id,
                'name' => $kitchen->display_name,
                'phone' => $kitchen->user->phone,
                'address' => $kitchen->pickup_address ?? $kitchen->area?->name,
                'contact' => ContactHours::card($kitchen->user),
            ],
            'slots' => $slots,
            'menus' => MyMenuController::rows($kitchen),
            'log' => ActForCook::today($kitchen),
        ]]);
    }

    /** POST /api/v1/shahidar/kitchens/{kitchen}/ready */
    public function ready(Request $request, Kitchen $kitchen): JsonResponse
    {
        $this->allow($request, $kitchen);
        $data = $request->validate(['meal_slot' => ['required', Rule::enum(MealSlot::class)], 'note' => ['nullable', 'string', 'max:200']]);
        $slot = MealSlot::from($data['meal_slot']);

        $n = $this->act->run($kitchen, $request->user(), 'ready', "আজ {$slot->label()}: খাবার রেডি জানানো হয়েছে".(filled($data['note'] ?? null) ? " — {$data['note']}" : ''),
            fn () => $this->actions->ready($kitchen, $slot, $data['note'] ?? null));

        return response()->json(['message' => 'রেডি জানানো হয়েছে ('.Order::banglaDigits($n).'টা বক্স), গৃহিণীকেও জানানো হয়েছে।']);
    }

    /** POST /api/v1/shahidar/kitchens/{kitchen}/off — needs a reason. */
    public function off(Request $request, Kitchen $kitchen): JsonResponse
    {
        $this->allow($request, $kitchen);
        $data = $request->validate([
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], ['reason.required' => 'কেন রান্না নেই লিখুন।', 'reason.min' => 'কেন রান্না নেই লিখুন।']);
        $slot = MealSlot::from($data['meal_slot']);

        $orders = $this->act->run($kitchen, $request->user(), 'off', "আজ {$slot->label()}: রান্না নেই — {$data['reason']}",
            fn () => $this->actions->off($kitchen, $slot));

        return response()->json(['message' => $orders->isEmpty() ? 'লিখে রাখা হয়েছে।' : Order::banglaDigits($orders->count()).' জন গ্রাহককে জানানো হচ্ছে।']);
    }

    /** PATCH /api/v1/shahidar/posts/{post} — plates left, dish name, note (not the price). */
    public function updatePost(Request $request, Post $post): JsonResponse
    {
        $this->allow($request, $post->kitchen);
        $data = $request->validate([
            'plates_left' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'title' => ['sometimes', 'string', 'min:2', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        if (array_key_exists('plates_left', $data)) {
            $this->act->run($post->kitchen, $request->user(), 'plates', "{$post->meal_slot->label()} পোস্ট \"{$post->title}\": ফিডে বাকি ".Order::banglaDigits((string) $data['plates_left']).' প্লেট',
                fn () => $this->actions->setPlates($post, (int) $data['plates_left']), post: $post);
        }
        if (array_key_exists('title', $data) || array_key_exists('note', $data)) {
            $title = $data['title'] ?? $post->title;
            $this->act->run($post->kitchen, $request->user(), 'edit', "{$post->meal_slot->label()} পোস্টের নাম/নোট ঠিক করা হয়েছে: {$title}",
                fn () => $this->actions->editPost($post, $title, array_key_exists('note', $data) ? $data['note'] : $post->note), post: $post);
        }

        return response()->json(['message' => 'বদলানো হয়েছে, গৃহিণীকে জানানো হয়েছে।']);
    }

    /** POST /api/v1/shahidar/posts/{post}/sold-out */
    public function soldOut(Request $request, Post $post): JsonResponse
    {
        $this->allow($request, $post->kitchen);
        $this->act->run($post->kitchen, $request->user(), 'sold_out', "{$post->meal_slot->label()} পোস্ট \"{$post->title}\": শেষ হয়ে গেছে",
            fn () => $this->actions->soldOut($post), post: $post);

        return response()->json(['message' => 'শেষ লেখা হয়েছে।']);
    }

    /** POST /api/v1/shahidar/kitchen-orders/{order}/decline */
    public function decline(Request $request, Order $order): JsonResponse
    {
        $this->allow($request, $order->kitchen);
        $this->act->run($order->kitchen, $request->user(), 'decline', "ফিডের অর্ডার বক্স {$order->code()}: দেওয়া যাচ্ছে না",
            fn () => $this->actions->decline($order, timeLimit: false), order: $order);

        return response()->json(['message' => 'বাতিল হয়েছে, ক্রেতাকে জানানো হচ্ছে।']);
    }

    /** POST /api/v1/shahidar/kitchen-orders/{order}/handed */
    public function handed(Request $request, Order $order): JsonResponse
    {
        $this->allow($request, $order->kitchen);
        $this->act->run($order->kitchen, $request->user(), 'handed', "বক্স {$order->code()}: ক্রেতা রান্নাঘর থেকে নিয়েছেন",
            fn () => $this->actions->handed($order, $request->user()), order: $order);

        return response()->json(['message' => 'দেওয়া হয়েছে, লিখে রাখা হলো।']);
    }

    /** PUT /api/v1/shahidar/kitchens/{kitchen}/menus/{slot} */
    public function saveMenu(Request $request, Kitchen $kitchen, string $slot): JsonResponse
    {
        $this->allow($request, $kitchen);
        $slot = MealSlot::tryFrom($slot) ?? abort(404);
        $data = MyMenuController::validateMenu($request);

        $this->act->run($kitchen, $request->user(), 'menu', "প্রতিদিনের মেনু ({$slot->label()}): {$data['title']}, ৳".Order::banglaDigits((string) $data['price_taka']).', '.ContactHours::clock($data['post_at']).'-এ',
            fn () => KitchenMenu::updateOrCreate(['kitchen_id' => $kitchen->id, 'meal_slot' => $slot->value], MyMenuController::attributes($data)));

        return response()->json(['data' => MyMenuController::rows($kitchen), 'message' => 'মেনু রাখা হয়েছে, গৃহিণীকে জানানো হয়েছে।']);
    }

    /** DELETE /api/v1/shahidar/kitchens/{kitchen}/menus/{slot} */
    public function deleteMenu(Request $request, Kitchen $kitchen, string $slot): JsonResponse
    {
        $this->allow($request, $kitchen);
        $slot = MealSlot::tryFrom($slot) ?? abort(404);
        $this->act->run($kitchen, $request->user(), 'menu', "প্রতিদিনের মেনু ({$slot->label()}) সরানো হয়েছে",
            fn () => KitchenMenu::where('kitchen_id', $kitchen->id)->where('meal_slot', $slot->value)->delete());

        return response()->json(['data' => MyMenuController::rows($kitchen), 'message' => 'মেনু সরানো হয়েছে।']);
    }

    private function allow(Request $request, ?Kitchen $kitchen): void
    {
        abort_unless($kitchen && $request->user()->isShahidar() && ShahidarController::worksWith($request->user(), $kitchen), 404);
    }
}
