<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishPost;
use App\Enums\MealSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Resources\OwnPostResource;
use App\Services\Images\UnreadablePhotoException;
use App\Services\Posts\PlateSplit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $post->load(['images', 'tags', 'orders']);

        return OwnPostResource::make($post)
            ->additional(['message' => 'পোস্ট হয়ে গেছে। '.PlateSplit::resultLine(app(PlateSplit::class)->forPost($post))])
            ->response()
            ->setStatusCode(201);
    }

    /** GET /api/v1/my/posts/preview — F44: before posting, where the plates will go (numbers only). */
    public function preview(Request $request, PlateSplit $split): JsonResponse
    {
        $kitchen = $request->user()->kitchen;
        abort_unless($kitchen, 404);
        $data = $request->validate([
            'meal_slot' => ['required', Rule::enum(MealSlot::class)],
            'plates' => ['required', 'integer', 'min:0', 'max:20'],
            'price_taka' => ['nullable', 'integer', 'min:0', 'max:2000'],
        ]);

        return response()->json(['data' => $split->preview(
            $kitchen, MealSlot::from($data['meal_slot']), (int) $data['plates'],
            isset($data['price_taka']) ? (int) $data['price_taka'] * 100 : null,
        )]);
    }
}
