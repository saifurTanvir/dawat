<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OwnPostResource;
use App\Models\Order;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** F10: the cook's own posts and quick updates during the day. */
class MyPostController extends Controller
{
    /** GET /api/v1/my/posts — last 7 days, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');

        $posts = $kitchen->posts()
            ->with(['images', 'tags', 'orders'])
            ->withCount('contactEvents')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->paginate(20);

        return OwnPostResource::collection($posts);
    }

    /** PATCH /api/v1/my/posts/{post} — how many plates are left. */
    public function update(Request $request, Post $post): OwnPostResource
    {
        Gate::authorize('manage', $post);

        // F26: plates booked by regular customers are not for sale.
        $booked = (int) $post->orders()->open()->sum('plates');
        $data = $request->validate(
            ['plates_left' => ['required', 'integer', 'min:0', 'max:'.($post->plates_total - $booked)]],
            ['plates_left.max' => $booked
                ? 'নিয়মিত গ্রাহকদের '.Order::banglaDigits($booked).' প্লেট বাদে সর্বোচ্চ :max প্লেট বাকি রাখা যাবে।'
                : 'মোট :max প্লেটের বেশি হতে পারবে না।'],
        );

        $post->plates_left = $data['plates_left'];
        $post->status = $post->plates_left === 0 ? PostStatus::SoldOut : PostStatus::Live;
        $post->save();

        return OwnPostResource::make($post->load(['images', 'tags']));
    }

    /** POST /api/v1/my/posts/{post}/sold-out */
    public function soldOut(Post $post): OwnPostResource
    {
        Gate::authorize('manage', $post);

        $post->update(['plates_left' => 0, 'status' => PostStatus::SoldOut]);

        return OwnPostResource::make($post->load(['images', 'tags']));
    }
}
