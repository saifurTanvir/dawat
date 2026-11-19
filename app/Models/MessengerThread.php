<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * F80: one person's Messenger chat with the page. The server answers it; an
 * admin can read it, answer from the panel, or turn the bot off for it.
 */
class MessengerThread extends Model
{
    /** When someone answers from the page itself, the bot keeps quiet this long. */
    public const PAUSE_HOURS = 6;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'bot_off' => 'boolean',
            'needs_human' => 'boolean',
            'paused_until' => 'datetime',
            'last_in_at' => 'datetime',
            'last_out_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(MessengerMessage::class)->latestOfMany('id');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function callRequest(): BelongsTo
    {
        return $this->belongsTo(CallRequest::class);
    }

    public function fact(string $key): mixed
    {
        return $this->facts[$key] ?? null;
    }

    /** Their last message has no answer yet (a minute's grace for the bot). */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNotNull('last_in_at')
            ->where('last_in_at', '<', now()->subMinute())
            ->where(fn ($q) => $q->whereNull('last_out_at')->orWhereColumn('last_out_at', '<', 'last_in_at'));
    }

    public function botMayAnswer(): bool
    {
        return ! $this->bot_off && ! ($this->paused_until?->isFuture());
    }

    /** Bangla state for the panel. */
    public function stateLabel(): string
    {
        return match (true) {
            $this->needs_human => 'মানুষ লাগবে',
            $this->bot_off => 'বট বন্ধ',
            (bool) $this->paused_until?->isFuture() => 'পেজ থেকে কেউ কথা বলছেন',
            default => 'বট উত্তর দিচ্ছে',
        };
    }
}
