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
        ]);

        $beat = OpsHeartbeat::create([
            'channel' => $data['channel'],
            'sent' => $data['sent'] ?? 0,
            'pending' => $data['pending'] ?? null,
            'note' => $data['note'] ?? null,
            'problem' => (bool) ($data['problem'] ?? false),
        ]);

        return response()->json(['data' => ['id' => $beat->id]], 201);
    }
}
