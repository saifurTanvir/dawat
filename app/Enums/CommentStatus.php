<?php

namespace App\Enums;

enum CommentStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Visible => 'দেখা যাচ্ছে',
            self::Hidden => 'লুকানো',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
