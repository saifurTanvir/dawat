<?php

namespace App\Models;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** F68: one office's boxes for one meal on one day, handed over together. */
class OfficeDrop extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    public const METHODS = [
        'office_code' => 'অফিসের কোড দিয়ে',
        'office_scan' => 'অফিস ব্যাগ স্ক্যান করে',
        'office_tap' => 'কোড ছাড়া (যাচাই বাকি)',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'meal_slot' => MealSlot::class,
            'delivered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** F95: the cooks' houses on the area manager's round for this drop. */
    public function stops(): HasMany
    {
        return $this->hasMany(RoundStop::class);
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(Sticker::class);
    }

    /** Boxes still on their way (or to come), not cancelled. */
    public function liveOrders()
    {
        return $this->orders()->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Failed]);
    }

    public function label(): string
    {
        return $this->company->name.' · '.$this->meal_slot->label();
    }
}
