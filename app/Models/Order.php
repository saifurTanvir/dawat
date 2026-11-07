<?php

namespace App\Models;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One box for one regular customer on one day and meal (F26). */
class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['delivery_code_hash'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'meal_slot' => MealSlot::class,
            'status' => OrderStatus::class,
            'picked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::Booked, OrderStatus::PickedUp]);
    }

    public function totalPaisa(): int
    {
        return $this->food_paisa + $this->delivery_fee_paisa;
    }

    /** The box code people say on the phone and write on a box: ০৮-৩৭. */
    public function code(): string
    {
        return self::banglaDigits($this->service_date->format('d').'-'.$this->seq);
    }

    public static function banglaDigits(string|int $value): string
    {
        return strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
