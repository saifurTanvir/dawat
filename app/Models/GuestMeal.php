<?php

namespace App\Models;

use App\Enums\MealSlot;
use App\Services\Orders\BookingPlanner;
use App\Support\DhakaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F64: extra plates a regular customer asked for, for a guest. */
class GuestMeal extends Model
{
    protected $guarded = ['id'];

    public const STATUS = [
        'asked' => 'গৃহিণীর উত্তরের অপেক্ষায়',
        'cook_yes' => 'গৃহিণী পারবেন',
        'cook_no' => 'গৃহিণী পারবেন না — অন্য ব্যবস্থা হচ্ছে',
        'arranged' => 'ব্যবস্থা হয়েছে',
        'cancelled' => 'বাতিল',
    ];

    public const OPEN = ['asked', 'cook_yes', 'cook_no'];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'meal_slot' => MealSlot::class,
            'plates' => 'integer',
            'cook_replied_at' => 'datetime',
            'followup_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** "আজ দুপুর" / "কাল রাত" */
    public function when(): string
    {
        $day = $this->service_date->toDateString();
        $today = BookingPlanner::today();
        $tomorrow = now(DhakaTime::TZ)->addDay()->toDateString();

        return ($day === $today ? 'আজ ' : ($day === $tomorrow ? 'কাল ' : $this->service_date->format('j M').' ')).$this->meal_slot->label();
    }
}
