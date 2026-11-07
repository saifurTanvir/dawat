<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QrController extends Controller
{
    /** Same phone re-opening the same code within this window counts once. */
    public const DEDUPE_MINUTES = 30;

    /**
     * GET /api/v1/qr/{code} — a printed QR was scanned (F15).
     * Tells the app which area to show and counts the scan.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $qr = QrCode::with('area')
            ->where('code', strtolower($code))
            ->where('is_active', true)
            ->first();

        if (! $qr || ! $qr->area?->is_active) {
            return response()->json(['message' => 'এই QR কোডটি আর চালু নেই। নিচে থেকে এলাকা বেছে নিন।'], 404);
        }

        $key = 'qr-scan:'.$qr->id.':'.sha1((string) $request->ip().'|'.$request->userAgent());
        if (Cache::add($key, true, now()->addMinutes(self::DEDUPE_MINUTES))) {
            $qr->increment('scans', 1, ['last_scanned_at' => now()]);
        }

        return response()->json([
            'data' => [
                'code' => $qr->code,
                'label' => $qr->label,
                'area' => AreaResource::make($qr->area),
            ],
        ]);
    }
}
