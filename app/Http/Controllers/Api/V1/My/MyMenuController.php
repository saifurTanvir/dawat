<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenMenu;
use App\Models\Order;
use App\Services\Posts\PlateSplit;
use App\Services\Posts\PostForCook;
use App\Support\ContactHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F43: the cook's daily menus, which post themselves at a set time. */
class MyMenuController extends Controller
{
    /** GET /api/v1/my/menus */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => self::rows($this->kitchen($request))]);
    }

    /** PUT /api/v1/my/menus/{slot} */
    public function save(Request $request, string $slot): JsonResponse
    {
        $kitchen = $this->kitchen($request);
        $slot = MealSlot::tryFrom($slot) ?? abort(404);
        $data = self::validateMenu($request);

        KitchenMenu::updateOrCreate(['kitchen_id' => $kitchen->id, 'meal_slot' => $slot->value], self::attributes($data));

        return response()->json(['data' => self::rows($kitchen), 'message' => 'মেনু রাখা হয়েছে।']);
    }

    /** DELETE /api/v1/my/menus/{slot} */
    public function destroy(Request $request, string $slot): JsonResponse
    {
        $kitchen = $this->kitchen($request);
        KitchenMenu::where('kitchen_id', $kitchen->id)->where('meal_slot', $slot)->delete();

        return response()->json(['data' => self::rows($kitchen), 'message' => 'মেনু সরানো হয়েছে।']);
    }

    /** @return array<string, mixed> */
    public static function validateMenu(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'price_taka' => ['required', 'integer', 'min:20', 'max:2000'],
            'plates_total' => ['required', 'integer', 'min:1', 'max:20'],
            'available_hours' => ['required', 'integer', 'min:1', 'max:6'],
            'post_at' => ['required', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:22:00'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'note' => ['nullable', 'string', 'max:300'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'title.required' => 'খাবারের নাম লিখুন, যেমন "ভাত, মাছের ঝোল, ডাল"।',
            'price_taka.min' => 'দাম কমপক্ষে ২০ টাকা।',
            'plates_total.max' => 'একবারে সর্বোচ্চ ২০ প্লেট।',
            'post_at.after_or_equal' => 'সকাল ৬টার পরের সময় দিন।',
            'post_at.before_or_equal' => 'রাত ১০টার আগের সময় দিন।',
            'weekdays.required' => 'অন্তত একটা দিন বাছুন।',
        ]);
    }

    /** @return array<string, mixed> */
    public static function attributes(array $data): array
    {
        return [
            'title' => trim($data['title']),
            'price_paisa' => (int) $data['price_taka'] * 100,
            'plates_total' => (int) $data['plates_total'],
            'available_hours' => (int) $data['available_hours'],
            'post_at' => $data['post_at'],
            'weekdays' => array_values(array_map('intval', $data['weekdays'])),
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    private function kitchen(Request $request): Kitchen
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');

        return $kitchen;
    }

    public static function rows(Kitchen $kitchen): array
    {
        return $kitchen->menus()->get()->map(fn (KitchenMenu $m) => [
            'meal_slot' => $m->meal_slot->value,
            'meal_slot_label' => $m->meal_slot->label(),
            'title' => $m->title,
            'price_taka' => intdiv($m->price_paisa, 100),
            'plates_total' => $m->plates_total,
            'available_hours' => $m->available_hours,
            'post_at' => $m->postAt(),
            'post_at_label' => ContactHours::clock($m->postAt()),
            'weekdays' => $m->weekdays,
            'weekdays_label' => count($m->weekdays) === 7 ? 'প্রতিদিন'
                : collect($m->weekdays)->sort()->map(fn ($d) => Customer::WEEKDAY_LABELS[$d])->implode(', '),
            'note' => $m->note,
            'active' => $m->active,
            'today' => PostForCook::menuToday($m),
            // F44: where today's plates would go if it posts now.
            'preview' => str_contains(PostForCook::menuToday($m), 'নিজে পোস্ট হবে')
                ? app(PlateSplit::class)->preview($kitchen, $m->meal_slot, $m->plates_total, $m->price_paisa)['line'] : null,
            'price_label' => '৳'.Order::banglaDigits((string) intdiv($m->price_paisa, 100)),
        ])->values()->all();
    }
}
