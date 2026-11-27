<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GuestMeal;
use App\Models\Order;
use App\Models\Post;
use App\Services\Regular\GuestMeals;
use App\Support\ContactHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F64: guest meals — the customer asks, the cook answers, the shahidar arranges. */
class GuestMealController extends Controller
{
    public function __construct(private readonly GuestMeals $guests) {}

    // ── customer ────────────────────────────────────────────────

    /** GET /api/v1/regular/guest-meals */
    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => self::forCustomer($this->customer($request))]);
    }

    /** POST /api/v1/regular/guest-meals {day: today|tomorrow, meal_slot, plates, note} */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'day' => ['required', Rule::in(['today', 'tomorrow'])],
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'plates' => ['required', 'integer', 'min:1', 'max:'.GuestMeals::MAX_PLATES],
            'note' => ['nullable', 'string', 'max:300'],
        ], [
            'day.required' => 'আজ না কাল, বেছে নিন।',
            'meal_slot.required' => 'কোন বেলা, বেছে নিন।',
            'plates.*' => 'অতিথির জন্য ১ থেকে ১০ প্লেট।',
        ]);

        $this->guests->request($customer, $data['day'], MealSlot::from($data['meal_slot']), (int) $data['plates'], $data['note'] ?? null);

        return response()->json([
            'data' => self::forCustomer($customer),
            'message' => 'অনুরোধ পাঠানো হয়েছে। গৃহিণী ও এলাকা ম্যানেজারকে জানানো হলো; ব্যবস্থা হলে আপনাকে জানাব।',
        ]);
    }

    /** POST /api/v1/regular/guest-meals/{guest}/cancel */
    public function cancel(Request $request, GuestMeal $guest): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($guest->customer_id === $customer->id, 404);
        $this->guests->cancelByCustomer($guest);

        return response()->json(['data' => self::forCustomer($customer), 'message' => 'অতিথির খাবারের অনুরোধ বাতিল হয়েছে।']);
    }

    public static function forCustomer(Customer $customer): array
    {
        return GuestMeals::current()->where('customer_id', $customer->id)->limit(10)->get()
            ->map(fn (GuestMeal $g) => [
                'id' => $g->id,
                'when' => $g->when(),
                'plates' => $g->plates,
                'note' => $g->note,
                'status' => $g->status,
                'status_label' => GuestMeal::STATUS[$g->status],
                'handled_note' => $g->handled_note,
                'order_code' => $g->order?->code(),
                'total_paisa' => $g->order?->totalPaisa(),
                'can_cancel' => $g->isOpen(),
            ])->values()->all();
    }

    // ── cook ────────────────────────────────────────────────────

    /** GET /api/v1/my/guest-meals — no customer names (F44). */
    public function forCook(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->cookRows($request)]);
    }

    /** POST /api/v1/my/guest-meals/{guest}/reply {yes} */
    public function reply(Request $request, GuestMeal $guest): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen && $guest->kitchen_id === $kitchen->id, 404);
        $data = $request->validate(['yes' => ['required', 'boolean']]);
        $this->guests->cookReply($guest, (bool) $data['yes'], $request->user());
        $guest->refresh();

        return response()->json([
            'data' => $this->cookRows($request),
            // QA re-check: say what actually happened — a box made now, or booked when she posts.
            'message' => match (true) {
                ! $data['yes'] => 'ঠিক আছে, এলাকা ম্যানেজার অন্য ব্যবস্থা করবেন।',
                $guest->order !== null => 'ধন্যবাদ! বক্স '.$guest->order->code().' তৈরি হয়েছে — '.Order::banglaDigits($guest->plates).' প্লেট বাড়তি রাঁধবেন, এলাকা ম্যানেজার অন্য বক্সের সাথে নিয়ে যাবেন।',
                default => 'ধন্যবাদ! আজকের পোস্ট দিলে এই প্লেটগুলো নিজে থেকে বুক হয়ে যাবে।',
            },
            'booked' => $guest->order !== null,
        ]);
    }

    private function cookRows(Request $request): array
    {
        $kitchen = $request->user()->kitchen;
        if (! $kitchen) {
            return [];
        }

        return GuestMeals::current()->where('kitchen_id', $kitchen->id)
            ->where(fn ($q) => $q->whereIn('status', ['asked', 'cook_yes'])->orWhereHas('order', fn ($o) => $o->where('kitchen_id', $kitchen->id)))
            ->limit(10)->get()->map(fn (GuestMeal $g) => [
                'id' => $g->id,
                'when' => $g->when(),
                'meal_slot' => $g->meal_slot->value,
                'plates' => $g->plates,
                'note' => $g->note,
                'status' => $g->status,
                'status_label' => GuestMeal::STATUS[$g->status],
                'can_reply' => $g->status === 'asked',
            ])->values()->all();
    }

    // ── shahidar ────────────────────────────────────────────────

    /** GET /api/v1/shahidar/guest-meals */
    public function forShahidar(Request $request): JsonResponse
    {
        return response()->json(['data' => self::shahidarRows($request->user()->id)]);
    }

    /** POST /api/v1/shahidar/guest-meals/{guest}/cook {yes} — after phoning the cook. */
    public function cookAnswered(Request $request, GuestMeal $guest): JsonResponse
    {
        $this->shahidarOf($request, $guest);
        $data = $request->validate(['yes' => ['required', 'boolean']]);
        $this->guests->cookReply($guest, (bool) $data['yes'], $request->user());

        return $this->shahidarDone($request, $data['yes'] ? 'গৃহিণী পারবেন — রাখা হলো।' : 'গৃহিণী পারবেন না — অন্য পোস্ট থেকে ব্যবস্থা করুন।');
    }

    /** GET /api/v1/shahidar/guest-meals/{guest}/posts — live posts with enough plates. */
    public function posts(Request $request, GuestMeal $guest): JsonResponse
    {
        $this->shahidarOf($request, $guest);

        return response()->json(['data' => $this->guests->candidatePosts($guest)->map(fn (Post $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'kitchen' => $p->kitchen->display_name,
            'area' => $p->kitchen->area?->name,
            'own_cook' => $p->kitchen_id === $guest->kitchen_id,
            'price_paisa' => $p->price_paisa,
            'plates_left' => $p->plates_left,
        ])->values()]);
    }

    /** POST /api/v1/shahidar/guest-meals/{guest}/arrange {post_id} — book from a live post. */
    public function arrange(Request $request, GuestMeal $guest): JsonResponse
    {
        $this->shahidarOf($request, $guest);
        $data = $request->validate(['post_id' => ['required', 'integer']]);
        $post = Post::findOrFail($data['post_id']);
        $order = $this->guests->arrangeFromPost($guest, $post, $request->user());

        return $this->shahidarDone($request, "বুক হয়েছে, বক্স {$order->code()}। গ্রাহককে জানানো হয়েছে।");
    }

    /** POST /api/v1/shahidar/guest-meals/{guest}/close {note, cancel} — arranged another way, or cannot. */
    public function close(Request $request, GuestMeal $guest): JsonResponse
    {
        $this->shahidarOf($request, $guest);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300'], 'cancel' => ['sometimes', 'boolean']]);
        $cancel = (bool) ($data['cancel'] ?? false);
        $this->guests->close($guest, $request->user(), $data['note'] ?? null, $cancel);

        return $this->shahidarDone($request, $cancel ? 'গ্রাহককে জানানো হয়েছে যে এবার হচ্ছে না।' : 'ব্যবস্থা হয়েছে — গ্রাহককে জানানো হয়েছে।');
    }

    public static function shahidarRows(int $shahidarId): array
    {
        return GuestMeals::current()->where('shahidar_id', $shahidarId)->limit(20)->get()
            ->map(function (GuestMeal $g) {
                $user = $g->customer->user;
                $cook = $g->kitchen?->user;

                return [
                    'id' => $g->id,
                    'when' => $g->when(),
                    'customer' => $user->name ?: $user->phone,
                    'phone' => $user->phone,
                    'area' => $g->customer->area?->name,
                    'address' => $g->customer->address,
                    'plates' => $g->plates,
                    'note' => $g->note,
                    'kitchen' => $g->kitchen?->display_name,
                    'cook_phone' => $cook?->phone,
                    'status' => $g->status,
                    'status_label' => GuestMeal::STATUS[$g->status],
                    'late' => $g->followup_at !== null && $g->status === 'asked',
                    'is_today' => $g->service_date->isSameDay(now('Asia/Dhaka')),
                    'open' => $g->isOpen(),
                    'order_code' => $g->order?->code(),
                    'handled_note' => $g->handled_note,
                    'cook_contact' => $cook ? ContactHours::card($cook, [$g->meal_slot], 'আসসালামু আলাইকুম, দাওয়াত থেকে বলছি। '.$g->when().'-এ '.$g->plates.' প্লেট বাড়তি রান্না করতে পারবেন?') : null,
                ];
            })->values()->all();
    }

    private function shahidarOf(Request $request, GuestMeal $guest): void
    {
        abort_unless($guest->shahidar_id === $request->user()->id, 404);
    }

    private function shahidarDone(Request $request, string $message): JsonResponse
    {
        return response()->json(['data' => self::shahidarRows($request->user()->id), 'message' => $message]);
    }

    private function customer(Request $request): Customer
    {
        return Customer::where('user_id', $request->user()->id)->firstOrFail();
    }
}
