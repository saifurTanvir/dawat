<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\RoundStop;
use App\Services\Regular\OfficeDrops;
use App\Services\Regular\OfficeRounds;
use App\Support\Geo\GeoPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F68: a shahidar's office drops — pick up a cook's boxes together, hand the drop over once. */
class ShahidarDropController extends Controller
{
    public function __construct(private readonly OfficeDrops $drops) {}

    /** POST /api/v1/shahidar/drops/{drop}/pickup {kitchen_id, sticker?} */
    public function pickup(Request $request, OfficeDrop $drop): JsonResponse
    {
        $this->mine($request, $drop);
        $data = $request->validate(['kitchen_id' => ['required', 'integer'], 'sticker' => ['nullable', 'string', 'max:200']]);
        $n = $this->drops->pickup($drop, Kitchen::findOrFail($data['kitchen_id']), $request->user(), $data['sticker'] ?? null);

        return $this->done($request, $drop, "{$n}টা বক্স পিকআপ হয়েছে।");
    }

    /** POST /api/v1/shahidar/drops/{drop}/deliver {code?, cash_received} */
    public function deliver(Request $request, OfficeDrop $drop): JsonResponse
    {
        $this->mine($request, $drop);
        $data = $request->validate(['code' => ['nullable', 'digits:4'], 'cash_received' => ['sometimes', 'boolean']], ['code.digits' => 'কোড ৪ অঙ্কের।']);
        $n = $this->drops->deliver($drop, $request->user(), $data['code'] ?? null, $request->boolean('cash_received'));

        return $this->done($request, $drop, "অফিসে {$n}টা বক্স বুঝিয়ে দেওয়া হয়েছে।");
    }

    /** GET /api/v1/shahidar/drops/{drop}/round — F95: the round, house by house, with the way to each. */
    public function round(Request $request, OfficeDrop $drop, OfficeRounds $rounds): JsonResponse
    {
        $this->mine($request, $drop);

        return response()->json(['data' => $rounds->forShahidar($drop, $request->user(), $this->kind($request))]);
    }

    /** POST /api/v1/shahidar/drops/{drop}/round/start {lat?, lng?} — "রওনা দিচ্ছি". */
    public function start(Request $request, OfficeDrop $drop, OfficeRounds $rounds): JsonResponse
    {
        $this->mine($request, $drop);
        $data = $request->validate(['lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180']]);
        $here = isset($data['lat'], $data['lng']) ? GeoPoint::make($data['lat'], $data['lng']) : null;
        $kind = $this->kind($request);
        $rounds->start($drop, $request->user(), $here, $kind);

        return response()->json(['data' => $rounds->forShahidar($drop, $request->user(), $kind), 'message' => 'রাউন্ড শুরু — প্রথম গৃহিণীকে জানানো হয়েছে।']);
    }

    /** POST /api/v1/shahidar/drops/{drop}/round/stops/{stop}/later — "পরে আসব". */
    public function later(Request $request, OfficeDrop $drop, RoundStop $stop, OfficeRounds $rounds): JsonResponse
    {
        abort_unless($stop->office_drop_id === $drop->id && $stop->shahidar_id === $request->user()->id && $stop->kind === $this->kind($request), 404);
        $rounds->later($stop);

        return response()->json(['data' => $rounds->forShahidar($drop, $request->user(), $stop->kind), 'message' => 'পরে আসবেন — আগে অন্য বাসায় যান।']);
    }

    /** POST /api/v1/shahidar/drops/{drop}/return/stops/{stop}/done — F110: "বক্স ও টাকা দিয়েছি". */
    public function returned(Request $request, OfficeDrop $drop, RoundStop $stop, OfficeRounds $rounds): JsonResponse
    {
        abort_unless($stop->office_drop_id === $drop->id && $stop->shahidar_id === $request->user()->id && $stop->isReturn(), 404);
        $stop = $rounds->returned($stop, $request->user());
        $paid = $stop->paid_paisa ? ' আর ৳'.number_format(intdiv($stop->paid_paisa, 100)) : '';

        return response()->json([
            'data' => $rounds->forShahidar($drop, $request->user(), OfficeRounds::RETURN),
            'message' => $stop->kitchen->display_name.': বক্স'.$paid.' দেওয়া লেখা হলো — উনি অ্যাপে নিশ্চিত করবেন।',
        ]);
    }

    /** /drops/{drop}/round… is the pickup round, /drops/{drop}/return… the evening return round (F110). */
    private function kind(Request $request): string
    {
        return str_contains($request->path(), '/return') ? OfficeRounds::RETURN : OfficeRounds::PICKUP;
    }

    private function mine(Request $request, OfficeDrop $drop): void
    {
        abort_unless($drop->orders()->where('shahidar_id', $request->user()->id)->exists(), 404);
    }

    private function done(Request $request, OfficeDrop $drop, string $message): JsonResponse
    {
        $row = $this->drops->forShahidar($request->user())->firstWhere('id', $drop->id);

        return response()->json(['data' => $row, 'message' => $message]);
    }
}
