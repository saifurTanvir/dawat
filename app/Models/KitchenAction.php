<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F46: one thing the admin or a shahidar did for a cook. */
class KitchenAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /** "দাওয়াত টিম (রাশেদ)" or "শাহিদার করিম" */
    public function whoLabel(): string
    {
        return $this->role === 'shahidar'
            ? 'শাহিদার '.($this->user?->name ?: '')
            : 'দাওয়াত টিম'.($this->user?->name ? " ({$this->user->name})" : '');
    }

    /** @return array{who: string, details: string, at: string|null} */
    public function toRow(): array
    {
        return ['who' => trim($this->whoLabel()), 'details' => $this->details, 'at' => $this->created_at?->toIso8601String()];
    }
}
