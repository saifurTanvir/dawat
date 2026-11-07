<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use App\Enums\KitchenStatus;
use App\Support\Geo\LocationFuzzer;
use Database\Factories\KitchenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'user_id', 'display_name', 'area_id', 'location', 'status',
    'allow_call', 'allow_message', 'allow_comment', 'contact_phone', 'contact_name', 'max_regular_customers',
])]
#[Hidden(['location', 'public_location'])]
class Kitchen extends Model
{
    /** @use HasFactory<KitchenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'location' => AsGeoPoint::class,
            'public_location' => AsGeoPoint::class,
            'status' => KitchenStatus::class,
            'approved_at' => 'datetime',
            'allow_call' => 'boolean',
            'allow_message' => 'boolean',
            'allow_comment' => 'boolean',
            'rating_avg' => 'decimal:2',
            'max_regular_customers' => 'integer',
            'contact_verified_at' => 'datetime',
            'pending_contact_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A new home gets a new public point; the same home keeps its point
        // forever, so repeated queries can't average the offset away.
        static::saving(function (Kitchen $kitchen) {
            if ($kitchen->isDirty('location')) {
                $kitchen->public_location = $kitchen->location ? LocationFuzzer::fuzz($kitchen->location) : null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function contactEvents(): HasManyThrough
    {
        return $this->hasManyThrough(ContactEvent::class, Post::class);
    }

    /** F25: regular customers paired with this cook (open pairings). */
    public function pairings(): HasMany
    {
        return $this->hasMany(Pairing::class)->whereNull('ended_at');
    }

    /** How many more regular customers this cook can take. */
    public function freeRegularSlots(): int
    {
        $taken = $this->pairings_count ?? $this->pairings()->count();

        return max(0, $this->max_regular_customers - $taken);
    }

    public function isApproved(): bool
    {
        return $this->status === KitchenStatus::Approved;
    }

    /** True once the cook has finished the one-time setup and can be approved. */
    public function isSetUp(): bool
    {
        return $this->location !== null && $this->area_id !== null;
    }

    /** The number buyers reach: a shahidar's if the cook chose one, otherwise the cook's. */
    public function publicContactPhone(): string
    {
        return $this->contact_phone ?: $this->user->phone;
    }

    /** A contact number is waiting for the cook's OTP or an admin. */
    public function hasPendingContact(): bool
    {
        return $this->pending_contact_phone !== null;
    }

    /** Ask to use another number; nothing changes for buyers until it is confirmed. */
    public function requestContact(string $phone, string $name, string $method): void
    {
        $this->forceFill([
            'pending_contact_phone' => $phone,
            'pending_contact_name' => $name,
            'pending_contact_method' => $method,
            'pending_contact_requested_at' => now(),
        ])->save();
    }

    /** The pending number becomes the one buyers reach. $via: otp | admin */
    public function confirmPendingContact(string $via): void
    {
        $this->forceFill([
            'contact_phone' => $this->pending_contact_phone,
            'contact_name' => $this->pending_contact_name,
            'contact_verified_via' => $via,
            'contact_verified_at' => now(),
        ])->clearPendingContact();
    }

    public function clearPendingContact(): void
    {
        $this->forceFill([
            'pending_contact_phone' => null,
            'pending_contact_name' => null,
            'pending_contact_method' => null,
            'pending_contact_requested_at' => null,
        ])->save();
    }

    /** Back to the cook's own number. */
    public function removeContact(): void
    {
        $this->forceFill([
            'contact_phone' => null,
            'contact_name' => null,
            'contact_verified_via' => null,
            'contact_verified_at' => null,
        ])->clearPendingContact();
    }

    public function approve(User $admin): void
    {
        $this->forceFill([
            'status' => KitchenStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejection_reason' => null,
        ])->save();
    }

    public function reject(User $admin, string $reason): void
    {
        $this->forceFill([
            'status' => KitchenStatus::Rejected,
            'approved_at' => null,
            'approved_by' => $admin->id,
            'rejection_reason' => $reason,
        ])->save();
    }

    public function suspend(User $admin, string $reason): void
    {
        $this->forceFill([
            'status' => KitchenStatus::Suspended,
            'approved_by' => $admin->id,
            'rejection_reason' => $reason,
        ])->save();
    }
}
