<?php

namespace App\Models;

use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['post_id', 'viewer_id', 'qr_code_id', 'type'])]
class ContactEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => ContactType::class];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }
}
