<?php

namespace App\Http\Resources;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Area */
class AreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Area centres are public landmarks, not anyone's home.
            'center' => $this->center?->toArray(),
            'distance_m' => $this->when(isset($this->distance_m), fn () => (int) round($this->distance_m)),
        ];
    }
}
