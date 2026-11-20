<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OpsHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F78: an admin's browser (where the reply loop runs) reports in. */
class OpsHeartbeatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(OpsHeartbeat::CHANNELS))],
            'sent' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'pending' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
            'problem' => ['nullable', 'boolean'],
            'found' => ['nullable', 'integer', 'min:0', 'max:5000'],
            // F83: per chat — who, what they wrote, what was done.
            'details' => ['nullable', 'array', 'max:200'],
            'details.*.who' => ['required', 'string', 'max:120'],
            'details.*.said' => ['nullable', 'string', 'max:300'],
            'details.*.did' => ['nullable', 'string', 'max:300'],
        ]);

        $beat = OpsHeartbeat::create([
            'channel' => $data['channel'],
            'sent' => $data['sent'] ?? 0,
            'pending' => $data['pending'] ?? null,
            'note' => $data['note'] ?? null,
            'problem' => (bool) ($data['problem'] ?? false),
            'found' => $data['found'] ?? null,
            'details' => isset($data['details'])
                ? collect($data['details'])->map(fn (array $d) => ['who' => $d['who'], 'said' => $d['said'] ?? null, 'did' => $d['did'] ?? null])->values()->all()
                : null,
        ]);

        return response()->json(['data' => ['id' => $beat->id]], 201);
    }
}
