<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MealSlot: string implements HasLabel
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Snack = 'snack';
    case Dinner = 'dinner';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'সকালের নাস্তা',
            self::Lunch => 'দুপুর',
            self::Snack => 'বিকেলের নাস্তা',
            self::Dinner => 'রাত',
        };
    }

    /** "রাতের খাবার", "সকালের নাস্তা": for running text (not "রাত-এর খাবার"). */
    public function food(): string
    {
        return match ($this) {
            self::Breakfast => 'সকালের নাস্তা',
            self::Lunch => 'দুপুরের খাবার',
            self::Snack => 'বিকেলের নাস্তা',
            self::Dinner => 'রাতের খাবার',
        };
    }

    /** "রাতের", "দুপুরের", "সকালের নাস্তার": "of this meal". */
    public function of(): string
    {
        return match ($this) {
            self::Breakfast => 'সকালের নাস্তার',
            self::Lunch => 'দুপুরের',
            self::Snack => 'বিকেলের নাস্তার',
            self::Dinner => 'রাতের',
        };
    }

    /** "রাতে", "দুপুরে", "সকালের নাস্তায়": "at this meal". */
    public function at(): string
    {
        return match ($this) {
            self::Breakfast => 'সকালের নাস্তায়',
            self::Lunch => 'দুপুরে',
            self::Snack => 'বিকেলের নাস্তায়',
            self::Dinner => 'রাতে',
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
