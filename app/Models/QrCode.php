<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['area_id', 'label', 'code', 'is_active'])]
class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (QrCode $qr) {
            $qr->code ??= static::newCode();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scans' => 'integer',
            'last_scanned_at' => 'datetime',
        ];
    }

    /** Short, unambiguous code for printed URLs (no 0/O, 1/l). */
    public static function newCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
            $code = strtr($code, ['0' => 'x', 'o' => 'y', '1' => 'z', 'l' => 'k']);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function contactEvents(): HasMany
    {
        return $this->hasMany(ContactEvent::class);
    }

    /** The address printed under the QR image. */
    public function url(): string
    {
        return url('/q/'.$this->code);
    }
}
