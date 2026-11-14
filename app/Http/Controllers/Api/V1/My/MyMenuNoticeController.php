<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\MenuNotice;
use App\Services\Regular\TodayMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F61: the cook tells her regular customers what she is cooking today. */
class MyMenuNoticeController extends Controller
{
    /** GET /api/v1/my/menu-notices — today's, by meal. The cook never sees customers' answers. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => self::rows($this->kitchen($request))]);
    }

    /** PUT /api/v1/my/menu-notices/{slot} {title} */
    public function save(Request $request, string $slot, TodayMenu $menu): JsonResponse
    {
        $kitchen = $this->kitchen($request);
        $slot = MealSlot::tryFrom($slot) ?? abort(404);
        $data = self::validateTitle($request);
        $menu->announce($kitchen, $slot, $data['title'], 'cook', $request->user());

        return response()->json(['data' => self::rows($kitchen), 'message' => 'জানানো হয়েছে।']);
    }

    public static function validateTitle(Request $request): array
    {
        return $request->validate(
            ['title' => ['required', 'string', 'min:2', 'max:150']],
            ['title.required' => 'কী রান্না হবে লিখুন।', 'title.min' => 'কী রান্না হবে লিখুন।', 'title.max' => '১৫০ অক্ষরের মধ্যে লিখুন।'],
        );
    }

    /** @return list<array{meal_slot: string, label: string, title: ?string, source: ?string, at: ?string}> */
    public static function rows(Kitchen $kitchen): array
    {
        $notices = TodayMenu::noticesToday($kitchen);

        return collect(MealSlot::cases())->map(function (MealSlot $slot) use ($notices) {
            $n = $notices->get($slot->value);

            return [
                'meal_slot' => $slot->value,
                'label' => $slot->label(),
                'title' => $n?->title,
                'source' => $n ? (MenuNotice::SOURCES[$n->source] ?? null) : null,
                'at' => $n?->updated_at?->toIso8601String(),
            ];
        })->all();
    }

    private function kitchen(Request $request): Kitchen
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen && $kitchen->isApproved(), 403, 'রান্নাঘর অনুমোদনের পরে জানাতে পারবেন।');

        return $kitchen;
    }
}
