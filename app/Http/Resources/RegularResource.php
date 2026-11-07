<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer's own regular-food booking. Shows the cook's kitchen name and the
 * shahidar's name and phone (who to call), never the cook's address or location.
 *
 * @mixin Customer
 */
class RegularResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pairing = $this->pairing;

        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'name' => $this->user->name,
            'area' => ['id' => $this->area->id, 'name' => $this->area->name],
            'address' => $this->address,
            'note' => $this->note,
            'meal_slots' => $this->meal_slots,
            'plates' => $this->plates,
            'weekdays' => $this->weekdays,
            'booking_summary' => $this->bookingSummary(),
            'kitchen' => $pairing ? ['name' => $pairing->kitchen->display_name] : null,
            'shahidar' => $pairing ? ['name' => $pairing->shahidar->name, 'phone' => $pairing->shahidar->phone] : null,
        ];
    }
}
