<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** F17: anyone signed in can flag a post or comment for the Dawat team. */
class ReportController extends Controller
{
    /** Distinct reporters with open reports before a post/comment is hidden pending review. */
    public const AUTO_HIDE_AT = 3;

    /** Only accounts at least this old count towards hiding, so fresh throwaway numbers can't. */
    public const TRUSTED_AFTER_DAYS = 3;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:post,comment'],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ], ['reason.required' => 'কারণ বেছে নিন।']);

        $target = $data['type'] === 'post' ? Post::find($data['id']) : Comment::find($data['id']);
        abort_unless($target, 404);

        $user = $request->user();
        $ownerId = $target instanceof Post ? $target->kitchen->user_id : $target->user_id;
        if ($ownerId === $user->id) {
            return response()->json(['message' => 'নিজের পোস্ট বা কমেন্ট রিপোর্ট করা যায় না।'], 422);
        }

        $report = Report::firstOrCreate(
            [
                'reporter_id' => $user->id,
                'reportable_type' => $target::class,
                'reportable_id' => $target->id,
                'status' => ReportStatus::Open,
            ],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null],
        );

        $this->autoHideIfNeeded($target);

        return response()->json([
            'message' => 'ধন্যবাদ। দাওয়াত টিম বিষয়টি দেখবে।',
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    private function autoHideIfNeeded(Model $target): void
    {
        $reporters = Report::where('reportable_type', $target::class)
            ->where('reportable_id', $target->getKey())
            ->where('status', ReportStatus::Open)
            ->whereHas('reporter', fn ($u) => $u->where('status', 'active')
                ->whereNotNull('phone_verified_at')
                ->where('created_at', '<=', now()->subDays(self::TRUSTED_AFTER_DAYS)))
            ->distinct()
            ->count('reporter_id');

        if ($reporters < self::AUTO_HIDE_AT) {
            return;
        }

        $target->update(['status' => $target instanceof Post ? PostStatus::Hidden : CommentStatus::Hidden]);
    }
}
