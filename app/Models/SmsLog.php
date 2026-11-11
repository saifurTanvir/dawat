<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F50: one attempt to reach a phone with a code. The code itself is never stored. */
class SmsLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_LABELS = ['sent' => 'পাঠানো হয়েছে', 'failed' => 'যায়নি', 'reported' => 'কোড পাননি (জানিয়েছেন)', 'whatsapp' => 'হোয়াটসঅ্যাপে কোড'];

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by');
    }
}
