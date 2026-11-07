<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A printed QR sticker (F27). Its QR opens /s/{code}; it carries no personal data. */
class Sticker extends Model
{
    protected $guarded = ['id'];

    /** No 0/O, 1/I/L: the code is also typed by hand when a camera fails. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return ['bound_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function newCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** Accepts the code, a /s/{code} URL, lower case, spaces. */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        if (preg_match('~/s/([A-Za-z0-9]+)~', $input, $m)) {
            $input = $m[1];
        }
        $code = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input));

        return strlen($code) === 8 ? $code : null;
    }

    public function url(): string
    {
        return url('/s/'.$this->code);
    }

    /** "ABCD EFGH" — easier to read off a box. */
    public function displayCode(): string
    {
        return substr($this->code, 0, 4).' '.substr($this->code, 4);
    }
}
