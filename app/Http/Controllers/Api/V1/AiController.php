<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Ai\AssistantUnavailableException;
use App\Services\Ai\FoodAssistant;
use App\Services\Images\FoodPhotoProcessor;
use App\Services\Images\UnreadablePhotoException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F20: suggestions while writing a post. Only for cooks who may post;
 * everything returned is a suggestion the cook can accept or change.
 */
class AiController extends Controller
{
    public function __construct(private readonly FoodAssistant $assistant) {}

    /** POST /api/v1/ai/photo (image) — dish name and tags from the first photo. */
    public function photo(Request $request, FoodPhotoProcessor $photos): JsonResponse
    {
        $request->validate(
            ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:12288']],
            ['image.required' => 'ছবি দিন।'],
        );

        if (! $this->assistant->available()) {
            return $this->off();
        }

        try {
            // Re-encoded small JPEG: no EXIF/GPS ever leaves the server.
            $jpeg = $photos->smallJpeg($request->file('image'));
            $tags = Tag::active()->pluck('name', 'slug')->all();

            return response()->json(['data' => $this->assistant->suggestFromPhoto($jpeg, $tags)->toArray()]);
        } catch (UnreadablePhotoException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['image' => [$e->getMessage()]]], 422);
        } catch (AssistantUnavailableException) {
            return $this->busy();
        }
    }

    /** POST /api/v1/ai/note {note, title} — tidies the cook's note, keeping every warning. */
    public function note(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:300'],
            'title' => ['nullable', 'string', 'max:100'],
        ], ['note.required' => 'আগে নিজের কথায় কিছু লিখুন।', 'note.min' => 'আগে নিজের কথায় কিছু লিখুন।']);

        if (! $this->assistant->available()) {
            return $this->off();
        }

        try {
            return response()->json(['data' => [
                'note' => $this->assistant->polishNote($data['note'], $data['title'] ?? ''),
            ]]);
        } catch (AssistantUnavailableException) {
            return $this->busy();
        }
    }

    private function off(): JsonResponse
    {
        return response()->json(['message' => 'এই সুবিধাটা এখন চালু নেই।'], 503);
    }

    private function busy(): JsonResponse
    {
        return response()->json(['message' => 'এখন সাজেশন দেওয়া যাচ্ছে না। নিজে লিখে পোস্ট করুন।'], 503);
    }
}
