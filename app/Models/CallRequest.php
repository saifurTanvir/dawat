<?php

namespace App\Models;

use App\Enums\CallRequestStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F66: someone the owner has to talk to himself ("আগে আমার সাথে কথা বলুন"),
 * from WhatsApp, Messenger, a comment or a call. Admins only.
 */
class CallRequest extends Model
{
    public const SOURCES = [
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'comment' => 'ফেসবুক কমেন্ট',
        'call' => 'ফোন কল',
        'other' => 'অন্য',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => CallRequestStatus::class, 'notes' => 'array', 'done_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (CallRequest $request) {
            $request->phone = PhoneNumber::normalize($request->phone) ?? $request->phone;
            if ($request->isDirty('phone') || ! $request->user_id) {
                $request->user_id = User::where('phone', $request->phone)->value('id');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** Not talked to yet (including "didn't pick up"). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', CallRequestStatus::Done);
    }

    public function telUrl(): string
    {
        return 'tel:+'.PhoneNumber::international($this->phone);
    }

    /** Opens the chat in WhatsApp; the call button is right there. */
    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.PhoneNumber::international($this->phone);
    }

    /** Adds a note to the history, newest first. */
    public function addNote(string $body, ?User $by): void
    {
        $this->notes = [
            ['at' => now()->toIso8601String(), 'by' => $by?->name, 'body' => trim($body)],
            ...($this->notes ?? []),
        ];
    }

    public function latestNote(): ?string
    {
        return $this->notes[0]['body'] ?? null;
    }
}
