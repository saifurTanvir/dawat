<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** F51: someone opened the app as another person. */
class Impersonation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'ends_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ImpersonationAction::class)->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->ended_at === null && $this->ends_at->isFuture();
    }

    /** "দাওয়াত টিম (রাশেদ)" or "শাহিদার করিম" */
    public function actorLabel(): string
    {
        return $this->actor_role === 'shahidar'
            ? 'শাহিদার '.($this->actor?->name ?? '')
            : 'দাওয়াত টিম'.($this->actor?->name ? " ({$this->actor->name})" : '');
    }
}
