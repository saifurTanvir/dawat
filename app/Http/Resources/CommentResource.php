<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public comment. Shows a first name only; never a phone number.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = auth('sanctum')->user();
        $cookId = $this->post->kitchen->user_id;
        $isCook = $this->user_id === $cookId;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'author' => [
                'name' => $isCook
                    ? $this->post->kitchen->display_name
                    : ($this->firstName($this->user->name) ?? 'দাওয়াতের একজন সদস্য'),
                'is_cook' => $isCook,
            ],
            'can_delete' => $viewer?->id === $this->user_id,
            'can_hide' => $viewer?->id === $cookId && ! $isCook,
            'can_report' => $viewer !== null && $viewer->id !== $this->user_id,
        ];
    }

    private function firstName(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : explode(' ', $name)[0];
    }
}
