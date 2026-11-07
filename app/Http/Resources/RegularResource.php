<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
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
            'max_price_paisa' => $this->max_price_paisa,
            'weekdays' => $this->weekdays,
            'booking_summary' => $this->bookingSummary(),
            'kitchen' => $pairing ? ['name' => $pairing->kitchen->display_name] : null,
            'shahidar' => $pairing ? ['name' => $pairing->shahidar->name, 'phone' => $pairing->shahidar->phone] : null,
            'today' => $this->whenLoaded('todayOrders', fn () => $this->todayOrders->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code(),
                'meal_slot_label' => $o->meal_slot->label(),
                'title' => $o->title,
                'plates' => $o->plates,
                'total_paisa' => $o->totalPaisa(),
                'status' => $o->status->value,
                'status_label' => $o->cancelled_by === 'cook' ? 'আজ রান্না নেই' : $o->status->label(),
                'can_skip' => in_array($o->status, [OrderStatus::Booked, OrderStatus::Asking], true),
                // F30: today's price is above the limit; "নেব" until ask_until.
                'can_accept' => $o->status === OrderStatus::Asking,
                'ask_until' => $o->ask_until?->toIso8601String(),
            ])->values()),
            'pauses' => $this->whenLoaded('upcomingPauses', fn () => $this->upcomingPauses->map(fn ($p) => [
                'id' => $p->id,
                'starts_on' => $p->starts_on->toDateString(),
                'ends_on' => $p->ends_on->toDateString(),
            ])->values()),
        ];
    }
}
