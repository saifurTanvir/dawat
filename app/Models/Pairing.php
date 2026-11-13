<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Customer ↔ cook ↔ shahidar (F25). Ended pairings stay as history. */
class Pairing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    /** F55: the cook the platform suggested when this pairing was made. */
    public function suggestedKitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class, 'suggested_kitchen_id');
    }

    public function suggestedShahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_shahidar_id');
    }

    /** F55: null when nothing was suggested; true when the admin took the suggested cook. */
    public function followedSuggestion(): ?bool
    {
        return $this->suggested_kitchen_id ? $this->suggested_kitchen_id === $this->kitchen_id : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
