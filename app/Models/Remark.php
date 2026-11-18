<?php

namespace App\Models;

use App\Services\Orders\BookingPlanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F63: an admin's note on a person, e.g. "OTP দেননি, কাল আবার ফোন". Admins only. */
class Remark extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date', 'done_at' => 'datetime'];
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

    /** Follow-ups not closed yet, due or not. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_on')->whereNull('done_at');
    }

    /** F69: closes this follow-up, with what happened. */
    public function close(?User $by, ?string $note): void
    {
        $note = trim((string) $note);
        $this->forceFill(['done_at' => now(), 'done_by' => $by?->id, 'done_note' => $note === '' ? null : mb_substr($note, 0, 500)])->save();
    }

    /** Follow-ups due today or earlier and not done. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_on')->whereNull('done_at')->whereDate('follow_up_on', '<=', BookingPlanner::today());
    }
}
