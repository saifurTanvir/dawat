<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F47: a customer's stars and words for one delivered box. */
class BoxReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stars' => 'integer', 'reply_hidden' => 'boolean', 'replied_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_by');
    }

    /** Who wrote it, without a name: for the cook. */
    public function reviewerLabel(): string
    {
        return $this->order?->isOneOff() ? 'ফিডের ক্রেতা' : 'একজন নিয়মিত গ্রাহক';
    }

    /** "গৃহিণী", "শাহিদার করিম", "দাওয়াত টিম" */
    public function replyLabel(): ?string
    {
        return match ($this->reply_role) {
            'cook' => 'গৃহিণী',
            'shahidar' => 'শাহিদার '.($this->replier?->name ?? ''),
            'admin' => 'দাওয়াত টিম',
            default => null,
        };
    }

    public function stars(): string
    {
        return str_repeat('★', $this->stars).str_repeat('☆', 5 - $this->stars);
    }
}
