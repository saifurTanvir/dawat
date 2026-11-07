<?php

namespace App\Enums;

enum PostStatus: string
{
    case Live = 'live';
    case SoldOut = 'sold_out';
    case Expired = 'expired';
    case Hidden = 'hidden';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Live => 'চালু',
            self::SoldOut => 'শেষ হয়ে গেছে',
            self::Expired => 'সময় শেষ',
            self::Hidden => 'লুকানো',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
