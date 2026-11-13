<?php

namespace App\Http\Resources;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Orders\BookingPlanner;
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
            // F55: whether a home pin is set; the pin itself is never sent.
            'has_location' => $this->location !== null,
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
                // F45: steps — booked, cook ready, on the way, delivered.
                'ready_at' => $o->ready_at?->toIso8601String(),
                'picked_at' => $o->picked_at?->toIso8601String(),
                'delivered_at' => $o->delivered_at?->toIso8601String(),
            ])->values()),
            // F45: meals of the booking with no box today, and why.
            'today_missing' => $this->whenLoaded('todayOrders', fn () => $this->status !== CustomerStatus::Active ? [] : collect(app(BookingPlanner::class)->todayReasons($this->resource))
                ->reject(fn ($r) => $this->todayOrders->contains(fn (Order $o) => $o->meal_slot->value === $r['slot']))
                ->map(fn ($r) => ['label' => $r['label'], 'line' => $r['line']])->values()),
            'pauses' => $this->whenLoaded('upcomingPauses', fn () => $this->upcomingPauses->map(fn ($p) => [
                'id' => $p->id,
                'starts_on' => $p->starts_on->toDateString(),
                'ends_on' => $p->ends_on->toDateString(),
            ])->values()),
        ];
    }
}
