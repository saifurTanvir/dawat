<?php

namespace App\Models;

use App\Enums\MealSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F43: a cook's standing menu for one meal; posted every chosen day at post_at. */
class KitchenMenu extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meal_slot' => MealSlot::class,
            'weekdays' => 'array',
            'active' => 'boolean',
            'price_paisa' => 'integer',
            'plates_total' => 'integer',
            'available_hours' => 'integer',
        ];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function postAt(): string
    {
        return substr((string) $this->post_at, 0, 5);
    }

    /** @return array<string, mixed> data in PublishPost's shape */
    public function postData(): array
    {
        return [
            'title' => $this->title,
            'price_taka' => intdiv($this->price_paisa, 100),
            'plates_total' => $this->plates_total,
            'meal_slot' => $this->meal_slot->value,
            'available_hours' => $this->available_hours,
            'note' => $this->note,
        ];
    }
}
