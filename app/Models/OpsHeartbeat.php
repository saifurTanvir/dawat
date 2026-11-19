<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
