<?php

namespace App\Http\Resources;

use App\Actions\PlaceOrder;
use App\Models\Post;
use App\Models\User;
use App\Support\Geo\PublicDistance;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post as buyers see it. Never contains coordinates or phone numbers:
 * the number is only handed out when a buyer taps call/message (F16).
 *
 * @mixin Post
 */
class FeedPostResource extends JsonResource
{
    /** Area has a shahidar? Remembered for the current request only. */
    private static function areaHasShahidar(?int $areaId): bool
    {
        if ($areaId === null) {
            return false;
        }
        $seen = request()->attributes->get('dawat.shahidar_areas', []);
        if (! array_key_exists($areaId, $seen)) {
            $seen[$areaId] = User::role(Roles::SHAHIDAR)->whereHas('shahidarAreas', fn ($q) => $q->whereKey($areaId))->exists();
            request()->attributes->set('dawat.shahidar_areas', $seen);
        }

        return $seen[$areaId];
    }

    public function toArray(Request $request): array
    {
        $kitchen = $this->kitchen;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price_paisa' => $this->price_paisa,
            'plates_total' => $this->plates_total,
            'plates_left' => $this->plates_left,
            'meal_slot' => $this->meal_slot->value,
            'meal_slot_label' => $this->meal_slot->label(),
            'note' => $this->note,
            'available_until' => $this->available_until->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'images' => $this->images->map(fn ($img) => [
                'url' => $img->url(),
                'width' => $img->width,
                'height' => $img->height,
            ]),
            'tags' => TagResource::collection($this->tags),
            'kitchen' => [
                'name' => $kitchen->display_name,
            ],
            'contact' => [
                'call' => $kitchen->allow_call,
                'message' => $kitchen->allow_message,
                'comment' => $kitchen->allow_comment,
            ],
            // F29: "নিতে চাই". Delivery only where the area has a shahidar.
            'order' => [
                'enabled' => $kitchen->allow_order,
                'delivery' => self::areaHasShahidar($kitchen->area_id),
                'delivery_fee_paisa' => (int) ($kitchen->area?->delivery_fee_paisa ?? 0),
                'max_plates' => min(PlaceOrder::MAX_PLATES, $this->plates_left),
            ],
            'share_image_url' => $this->shareImageUrl(),
            'comments_count' => (int) ($this->comments_count ?? 0),
            // Lets the app show the cook their own controls; no id is exposed.
            'is_mine' => auth('sanctum')->id() !== null && auth('sanctum')->id() === $kitchen->user_id,
            'distance_m' => $this->when(isset($this->distance_m), fn () => PublicDistance::round((float) $this->distance_m)),
            'walk_minutes' => $this->when(isset($this->distance_m), fn () => PublicDistance::walkMinutes((float) $this->distance_m)),
        ];
    }
}
