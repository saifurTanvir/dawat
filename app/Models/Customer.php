<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Services\Orders\BookingPlanner;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A regular customer (F25) with a standing booking (F26). */
#[Fillable(['user_id', 'area_id', 'address', 'note', 'status', 'meal_slots', 'plates', 'max_price_paisa', 'weekdays', 'source'])]
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
            'max_price_paisa' => 'integer',
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

    public function pauses(): HasMany
    {
        return $this->hasMany(CustomerPause::class)->orderBy('starts_on');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Today's boxes (Dhaka date). */
    public function todayOrders(): HasMany
    {
        return $this->hasMany(Order::class)->whereDate('service_date', BookingPlanner::today())->orderBy('id');
    }

    /** Days off that have not ended yet. */
    public function upcomingPauses(): HasMany
    {
        return $this->hasMany(CustomerPause::class)->whereDate('ends_on', '>=', BookingPlanner::today())->orderBy('starts_on');
    }

    public function isPausedOn(string $date): bool
    {
        return $this->pauses()->covering($date)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /** "দুপুর, রাত · ১ প্লেট · রবি–শনি" style summary for lists. */
    public function bookingSummary(): string
    {
        $slots = collect(MealSlot::cases())->filter(fn (MealSlot $m) => in_array($m->value, $this->meal_slots, true))
            ->map(fn (MealSlot $m) => $m->label())->implode(', ');
        $days = collect($this->weekdays)->sort()->values();
        $dayText = $days->count() === 7 ? 'প্রতিদিন' : $days->map(fn ($d) => self::WEEKDAY_LABELS[$d] ?? '')->implode(', ');

        $plates = Order::banglaDigits($this->plates);

        $limit = $this->max_price_paisa ? ' · প্লেট সর্বোচ্চ ৳'.Order::banglaDigits(intdiv($this->max_price_paisa, 100)) : '';

        return "{$slots} · {$plates} প্লেট · {$dayText}{$limit}";
    }
}
