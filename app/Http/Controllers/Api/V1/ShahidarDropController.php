<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Services\Regular\OfficeDrops;
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
