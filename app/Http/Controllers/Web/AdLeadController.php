<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Ads\BaddaAds;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F93: whoever answers WhatsApp notes each person who came from a Badda ad (admin only). */
class AdLeadController extends Controller
{
    public function store(Request $request, BaddaAds $ads): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'leads' => ['required', 'array', 'max:50'],
            'leads.*.ad' => ['required', Rule::in(array_keys(BaddaAds::ADS))],
            'leads.*.channel' => ['nullable', Rule::in(['whatsapp', 'comment'])],
            'leads.*.phone' => ['required', 'string', 'max:120'], // WhatsApp: the number; comment: any key for that comment
            'leads.*.name' => ['nullable', 'string', 'max:120'],
            'leads.*.text' => ['nullable', 'string', 'max:500'],
            'leads.*.replied' => ['boolean'],
        ]);

        $saved = collect($data['leads'])->map(fn ($l) => ($l['channel'] ?? 'whatsapp') === 'comment'
            ? $ads->noteComment($l['ad'], $l['phone'], $l['name'] ?? null, $l['text'] ?? null, (bool) ($l['replied'] ?? false))->id
            : $ads->noteWhatsApp(
                $l['ad'], PhoneNumber::normalize($l['phone']) ?? preg_replace('/\D/', '', $l['phone']),
                $l['name'] ?? null, $l['text'] ?? null, (bool) ($l['replied'] ?? false),
            )->id);

        return response()->json(['saved' => $saved->count()]);
    }
}
