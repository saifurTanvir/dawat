<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishPost;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Resources\OwnPostResource;
use App\Services\Images\UnreadablePhotoException;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    /** POST /api/v1/posts — publish today's food (F6–F9). */
    public function store(StorePostRequest $request, PublishPost $publish): JsonResponse
    {
        $kitchen = $request->user()->kitchen;

        if ($kitchen->posts()->where('created_at', '>=', today())->count() >= 8) {
            return response()->json(['message' => 'আজ সর্বোচ্চ ৮টা পোস্ট দেওয়া যায়। কাল আবার দিন।'], 429);
        }

        try {
            $post = $publish->handle($kitchen, $request->validated(), $request->file('images', []));
        } catch (UnreadablePhotoException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['images' => [$e->getMessage()]],
            ], 422);
        }

        return OwnPostResource::make($post->load(['images', 'tags']))
            ->additional(['message' => 'পোস্ট হয়ে গেছে। আশেপাশের মানুষ এখন দেখতে পাবেন।'])
            ->response()
            ->setStatusCode(201);
    }
}
