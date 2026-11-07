<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cook's own post. Never includes the location.
 *
 * @mixin Post
 */
class OwnPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price_paisa' => $this->price_paisa,
            'plates_total' => $this->plates_total,
            'plates_left' => $this->plates_left,
            'meal_slot' => $this->meal_slot->value,
            'meal_slot_label' => $this->meal_slot->label(),
            'note' => $this->note,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'available_until' => $this->available_until->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'url' => $img->url(),
                'width' => $img->width,
                'height' => $img->height,
            ])),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'contact_counts' => $this->when(isset($this->contact_events_count), fn () => $this->contact_events_count),
        ];
    }
}
