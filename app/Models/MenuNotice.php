<?php

namespace App\Models;

use App\Enums\MealSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F61: what a cook says she is cooking for a meal today. */
class MenuNotice extends Model
{
    protected $guarded = ['id'];

    public const SOURCES = ['cook' => 'গৃহিণী', 'shahidar' => 'এলাকা ম্যানেজার', 'admin' => 'দাওয়াত টিম', 'daily_menu' => 'রোজকার মেনু'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'meal_slot' => MealSlot::class];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }
}
