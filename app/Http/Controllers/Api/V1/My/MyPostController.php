<?php

namespace App\Http\Controllers\Api\V1\My;

use App\Enums\ContactType;
use App\Http\Controllers\Controller;
use App\Http\Resources\OwnPostResource;
use App\Models\Post;
use App\Services\Kitchens\CookActions;
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
            ->with(['images', 'tags', 'orders', 'kitchen.user'])
            ->withCount('contactEvents')
            // What the taps were — a call or WhatsApp tap only opens the buyer's own phone.
            ->withCount([
                'contactEvents as call_taps' => fn ($q) => $q->where('type', ContactType::Call),
                'contactEvents as message_taps' => fn ($q) => $q->where('type', ContactType::Message),
                'contactEvents as share_taps' => fn ($q) => $q->where('type', ContactType::Share),
                'contactEvents as comment_taps' => fn ($q) => $q->where('type', ContactType::Comment),
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->paginate(20);

        return OwnPostResource::collection($posts);
    }

    /** PATCH /api/v1/my/posts/{post} — how many plates are left. */
    public function update(Request $request, Post $post, CookActions $actions): OwnPostResource
    {
        Gate::authorize('manage', $post);
        $data = $request->validate(['plates_left' => ['required', 'integer'], 'cooked_more' => ['sometimes', 'boolean']]);
        $actions->setPlates($post, (int) $data['plates_left'], $request->boolean('cooked_more'));

        return OwnPostResource::make($post->load(['images', 'tags']));
    }

    /** POST /api/v1/my/posts/{post}/sold-out */
    public function soldOut(Post $post, CookActions $actions): OwnPostResource
    {
        Gate::authorize('manage', $post);
        $actions->soldOut($post);

        return OwnPostResource::make($post->load(['images', 'tags']));
    }
}
