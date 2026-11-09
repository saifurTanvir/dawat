<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\ContactHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own profile. Only ever returned to that user.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kitchen = $this->kitchen;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'roles' => $this->getRoleNames()->values(),
            // F41: when they may be called.
            'contact' => ContactHours::forMe($this->resource),
            'kitchen' => $kitchen ? [
                'id' => $kitchen->id,
                'status' => $kitchen->status->value,
                'status_label' => $kitchen->status->label(),
            ] : null,
        ];
    }
}
