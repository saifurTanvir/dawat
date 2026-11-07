<?php

namespace App\Enums;

enum TagType: string
{
    case Diet = 'diet';
    case Spice = 'spice';
    case Oil = 'oil';
    case Warning = 'warning';
    case Other = 'other';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Diet => 'খাদ্যাভ্যাস',
            self::Spice => 'ঝাল',
            self::Oil => 'তেল',
            self::Warning => 'সতর্কতা',
            self::Other => 'অন্যান্য',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
