<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportReason: string implements HasLabel
{
    case UnsafeFood = 'unsafe_food';
    case WrongInfo = 'wrong_info';
    case Rude = 'rude';
    case Spam = 'spam';
    case Other = 'other';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::UnsafeFood => 'খাবার খারাপ বা অস্বাস্থ্যকর',
            self::WrongInfo => 'ছবি বা তথ্য সত্যি নয়',
            self::Rude => 'অশালীন বা অপমানজনক',
            self::Spam => 'স্প্যাম বা বিজ্ঞাপন',
            self::Other => 'অন্য কিছু',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
