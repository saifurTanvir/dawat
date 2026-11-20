<?php

namespace App\Models;

use App\Enums\MealSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F61: a regular customer's answer for one meal today.
 * eat = "খাব" (the default, said out loud); skip = "আজ খাব না"; change = "এই খাবার খাব না" — the shahidar or an admin
 * arranges other food and marks it handled.
 */
class MealChoice extends Model
{
    protected $guarded = ['id'];

    public const CHOICES = ['eat' => 'খাবেন বলেছেন', 'skip' => 'আজ খাবেন না', 'change' => 'এই খাবার খাবেন না', 'pick' => 'অন্য গৃহিণীর খাবার নিয়েছেন'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'meal_slot' => MealSlot::class, 'handled_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /** F85: the other cook's post they picked, and the box made from it. */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
