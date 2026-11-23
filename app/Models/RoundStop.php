<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F95: one cook's house on an area manager's office round. */
class RoundStop extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'eta_at' => 'datetime',
            'soon_sent_at' => 'datetime',
            'next_sent_at' => 'datetime',
            'cook_ready_at' => 'datetime',
            'picked_at' => 'datetime',
            'sms_sent' => 'boolean',
        ];
    }

    public function drop(): BelongsTo
    {
        return $this->belongsTo(OfficeDrop::class, 'office_drop_id');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('picked_at');
    }

    public function scopeStarted(Builder $query): Builder
    {
        return $query->whereNotNull('started_at');
    }
}
