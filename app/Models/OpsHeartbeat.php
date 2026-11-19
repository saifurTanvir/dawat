<?php

namespace App\Models;

use App\Support\DhakaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** F78: one check-in from a reply loop — how many it answered, how many still wait, any problem. */
class OpsHeartbeat extends Model
{
    public const CHANNELS = ['messenger' => 'Messenger', 'whatsapp' => 'WhatsApp', 'comments' => 'কমেন্ট'];

    /** A loop that has not checked in for this long is shown as stopped. */
    public const STALE_MINUTES = 30;

    public const UPDATED_AT = null;

    protected $fillable = ['channel', 'sent', 'pending', 'note', 'problem'];

    protected function casts(): array
    {
        return ['sent' => 'integer', 'pending' => 'integer', 'problem' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * Per channel: label, state (ok / problem / stopped / none), last check-in, replies today.
     *
     * @return Collection<string, array{label: string, state: string, last: ?self, sentToday: int}>
     */
    public static function summary(): Collection
    {
        $today = DhakaTime::startOfDay();

        return collect(self::CHANNELS)->map(function (string $label, string $key) use ($today) {
            $last = self::query()->where('channel', $key)->latest('id')->first();

            return [
                'label' => $label,
                'state' => match (true) {
                    $last === null => 'none',
                    $last->created_at->lt(now()->subMinutes(self::STALE_MINUTES)) => 'stopped',
                    $last->problem => 'problem',
                    default => 'ok',
                },
                'last' => $last,
                'sentToday' => (int) self::query()->where('channel', $key)->where('created_at', '>=', $today)->sum('sent'),
            ];
        });
    }
}
