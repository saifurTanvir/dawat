<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A regular customer (F25) with a standing booking (F26). */
#[Fillable(['user_id', 'area_id', 'address', 'note', 'status', 'meal_slots', 'plates', 'weekdays', 'source'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /** Every day of the week, 0 = Sunday … 6 = Saturday. */
    public const ALL_WEEKDAYS = [0, 1, 2, 3, 4, 5, 6];

    public const WEEKDAY_LABELS = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'meal_slots' => 'array',
            'weekdays' => 'array',
            'plates' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(Pairing::class)->latest('started_at');
    }

    /** The open pairing, if any. */
    public function pairing(): HasOne
    {
        return $this->hasOne(Pairing::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /** "দুপুর, রাত · ১ প্লেট · রবি–শনি" style summary for lists. */
    public function bookingSummary(): string
    {
        $slots = collect($this->meal_slots)->map(fn ($s) => MealSlot::tryFrom($s)?->label())->filter()->implode(', ');
        $days = collect($this->weekdays)->sort()->values();
        $dayText = $days->count() === 7 ? 'প্রতিদিন' : $days->map(fn ($d) => self::WEEKDAY_LABELS[$d] ?? '')->implode(', ');

        $plates = strtr((string) $this->plates, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);

        return "{$slots} · {$plates} প্লেট · {$dayText}";
    }
}
