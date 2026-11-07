<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use App\Enums\KitchenStatus;
use Database\Factories\KitchenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'display_name', 'area_id', 'location', 'status',
    'allow_call', 'allow_message', 'allow_comment', 'contact_phone', 'contact_name',
])]
#[Hidden(['location'])]
class Kitchen extends Model
{
    /** @use HasFactory<KitchenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'location' => AsGeoPoint::class,
            'status' => KitchenStatus::class,
            'approved_at' => 'datetime',
            'allow_call' => 'boolean',
            'allow_message' => 'boolean',
            'allow_comment' => 'boolean',
            'rating_avg' => 'decimal:2',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
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
