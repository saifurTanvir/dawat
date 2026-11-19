<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F80: one message in a Messenger chat, theirs or ours. */
class MessengerMessage extends Model
{
    public const UPDATED_AT = null;

    public const BY = ['person' => 'উনি', 'bot' => 'বট', 'admin' => 'অ্যাডমিন (প্যানেল)', 'page' => 'পেজ থেকে'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'created_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessengerThread::class, 'messenger_thread_id');
    }
}
