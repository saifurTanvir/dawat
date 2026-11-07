<?php

namespace App\Http\Resources;

use App\Models\Kitchen;
use App\Services\Ai\FoodAssistant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cook's view of their own kitchen. Location is reduced to "set or not";
 * coordinates never leave the server.
 *
 * @mixin Kitchen
 */
class OwnKitchenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'is_set_up' => $this->isSetUp(),
            'can_post' => $request->user()?->can('publish-posts') ?? false,
            // F20: show the AI buttons only when an assistant is configured.
            'ai_available' => app(FoodAssistant::class)->available(),
            'area' => $this->whenLoaded('area', fn () => $this->area ? ['id' => $this->area->id, 'name' => $this->area->name] : null),
            'has_location' => $this->location !== null,
            'allow_call' => $this->allow_call,
            'allow_message' => $this->allow_message,
            'allow_comment' => $this->allow_comment,
            'contact_phone' => $this->contact_phone,
            'contact_name' => $this->contact_name,
            'contact_verified_via' => $this->contact_verified_via,
            'pending_contact' => $this->hasPendingContact() ? [
                'phone' => $this->pending_contact_phone,
                'name' => $this->pending_contact_name,
                'method' => $this->pending_contact_method,
                'requested_at' => $this->pending_contact_requested_at?->toIso8601String(),
            ] : null,
        ];
    }
}
