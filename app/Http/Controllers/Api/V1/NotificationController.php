<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\Admin\Impersonate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F66: the 🔔 list — what was sent, what it is about, where it opens. */
class NotificationController extends Controller
{
    public const PAGE = 50;

    /** GET /api/v1/notifications */
    public function index(Request $request): JsonResponse
    {
        $rows = AppNotification::where('user_id', $request->user()->id)->latest('id')->limit(self::PAGE)->get();

        return response()->json([
            'data' => $rows->map(fn (AppNotification $n) => self::row($n))->values(),
            'unread' => $this->unreadOf($request),
        ]);
    }

    /** GET /api/v1/notifications/unread — for the number on the bell. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->unreadOf($request)]);
    }

    /** POST /api/v1/notifications/{id}/read — returns where to go. */
    public function read(Request $request, int $id): JsonResponse
    {
        $note = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
        if (! $note->read_at && ! $this->viewingAs($request)) {
            $note->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => self::row($note), 'unread' => $this->unreadOf($request)]);
    }

    /** POST /api/v1/notifications/read-all */
    public function readAll(Request $request): JsonResponse
    {
        if (! $this->viewingAs($request)) {
            AppNotification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);
        }

        return response()->json(['unread' => $this->unreadOf($request), 'message' => 'সব পড়া হয়েছে।']);
    }

    public static function row(AppNotification $n): array
    {
        $section = $n->section();

        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'url' => $n->url,
            'section' => $section['key'],
            'section_label' => $section['label'],
            'icon' => $section['icon'],
            'read' => $n->read_at !== null,
            'at' => $n->created_at->toIso8601String(),
        ];
    }

    private function unreadOf(Request $request): int
    {
        return AppNotification::where('user_id', $request->user()->id)->unread()->count();
    }

    /** F51: an admin looking through someone's app doesn't mark their notifications read. */
    private function viewingAs(Request $request): bool
    {
        return Impersonate::current($request->user()) !== null;
    }
}
