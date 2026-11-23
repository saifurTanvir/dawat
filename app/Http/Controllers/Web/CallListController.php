<?php

namespace App\Http\Controllers\Web;

use App\Enums\CallRequestStatus;
use App\Filament\Actions\CallRequestActions;
use App\Http\Controllers\Controller;
use App\Models\CallRequest;
use App\Models\Kitchen;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F87: admins only — the open call list as JSON, and a note/close per entry, so the
 * list can be worked through from the admin's own browser session (no location data).
 */
class CallListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $open = CallRequest::query()->where('status', '!=', CallRequestStatus::Done)->oldest()->get();
        $kitchens = Kitchen::query()->with(['user:id,phone', 'area:id,name'])
            ->whereHas('user', fn ($q) => $q->whereIn('phone', $open->pluck('phone')->map(fn ($p) => PhoneNumber::normalize($p))->filter()))
            ->get()->keyBy(fn (Kitchen $k) => $k->user->phone);

        return response()->json(['data' => $open->map(function (CallRequest $r) use ($kitchens) {
            $k = $r->phone ? ($kitchens[PhoneNumber::normalize($r->phone)] ?? null) : null;

            return [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'source' => $r->source,
                'tag' => $r->tag,
                'status' => $r->status->value,
                'reason' => $r->reason,
                'notes' => collect($r->notes ?? [])->map(fn ($n) => ($n['at'] ?? '').' '.($n['body'] ?? ''))->values(),
                'created' => $r->created_at?->toIso8601String(),
                'kitchen' => $k ? ['id' => $k->id, 'status' => $k->status->value, 'area' => $k->area?->name, 'pinned' => $k->location !== null, 'plates' => $k->daily_plates] : null,
            ];
        })->values()]);
    }

    public function note(Request $request, CallRequest $callRequest): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000'], 'close' => ['boolean']]);

        $callRequest->addNote($data['body'], $request->user());
        if ($data['close'] ?? false) {
            CallRequestActions::setStatus($callRequest, CallRequestStatus::Done);
        }
        $callRequest->save();

        return response()->json(['data' => ['id' => $callRequest->id, 'status' => $callRequest->status->value]]);
    }
}
