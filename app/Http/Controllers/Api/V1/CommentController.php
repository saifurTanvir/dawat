<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommentStatus;
use App\Enums\ContactType;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\ContactEvent;
use App\Models\Post;
use App\Services\Push\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** F17: public comments under a post. */
class CommentController extends Controller
{
    /** GET /api/v1/posts/{post}/comments — visible comments, oldest first. */
    public function index(Post $post): AnonymousResourceCollection
    {
        abort_if($post->status === PostStatus::Hidden || ! $post->kitchen->isApproved(), 404);

        $comments = $post->comments()
            ->where('status', CommentStatus::Visible)
            ->with(['user:id,name', 'post.kitchen:id,user_id,display_name'])
            ->oldest('id')
            ->paginate(30);

        return CommentResource::collection($comments);
    }

    /** POST /api/v1/posts/{post}/comments {body} — signed-in users only. */
    public function store(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        $kitchen = $post->kitchen;
        $isCook = $kitchen->user_id === $user->id;

        if ($post->status === PostStatus::Hidden || ! $kitchen->isApproved()) {
            abort(404);
        }
        if (! $kitchen->allow_comment && ! $isCook) {
            return response()->json(['message' => 'রাঁধুনি এই পোস্টে কমেন্ট বন্ধ রেখেছেন।'], 403);
        }
        // Buyers comment while the food is on and for a day after; the cook can always answer.
        if (! $isCook && ! $post->commentsOpen() && ! Post::visibleNow()->whereKey($post->id)->exists()) {
            return response()->json(['message' => 'এই পোস্টে কমেন্টের সময় শেষ।'], 410);
        }

        $data = $request->validate(
            ['body' => ['required', 'string', 'min:2', 'max:500']],
            ['body.required' => 'কিছু লিখুন।', 'body.min' => 'কিছু লিখুন।', 'body.max' => 'কমেন্ট ৫০০ অক্ষরের মধ্যে রাখুন।'],
        );

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'body' => trim($data['body']),
            'status' => CommentStatus::Visible,
        ]);

        if (! $isCook) {
            ContactEvent::create(['post_id' => $post->id, 'viewer_id' => $user->id, 'type' => ContactType::Comment]);
        }
        $this->tell($post, $comment, $isCook);

        return CommentResource::make($comment->load(['user:id,name', 'post.kitchen']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/comments/{comment}
     * The author deletes their own comment; the cook hides comments on their post.
     */
    public function destroy(Request $request, Comment $comment): Response|JsonResponse
    {
        $user = $request->user();

        if ($comment->user_id === $user->id) {
            $comment->delete();

            return response()->noContent();
        }

        if ($comment->post->kitchen->user_id === $user->id) {
            $comment->update(['status' => CommentStatus::Hidden]);

            return response()->noContent();
        }

        return response()->json(['message' => 'এই কমেন্টটি আপনি সরাতে পারবেন না।'], 403);
    }

    /**
     * QA re-check: a question on a sold-out post went unanswered because nobody heard of it.
     * A buyer's comment reaches the cook; her answer reaches everyone who asked on that post.
     */
    private function tell(Post $post, Comment $comment, bool $isCook): void
    {
        $url = "/p/{$post->id}#comments";
        $text = Str::limit($comment->body, 120);

        if (! $isCook) {
            Push::toUser($post->kitchen->user_id, "নতুন কমেন্ট: {$post->title}", "{$text} — উত্তর দিন।", $url, "comment-{$post->id}");

            return;
        }

        $askers = $post->comments()->where('user_id', '!=', $comment->user_id)->distinct()->pluck('user_id');
        foreach ($askers as $askerId) {
            Push::toUser((int) $askerId, "{$post->kitchen->display_name} উত্তর দিয়েছেন", $text, $url, "comment-{$post->id}");
        }
    }
}
