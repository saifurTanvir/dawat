<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CustomerStatus: string implements HasColor, HasLabel
{
    /** Asked for regular food; waiting for an admin to pair them with a cook. */
    case Waiting = 'waiting';
    /** Paired; gets food on their booked days. */
    case Active = 'active';
    /** No longer wants regular food. */
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'জুটির অপেক্ষায়',
            self::Active => 'চালু',
            self::Stopped => 'বন্ধ',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Active => 'success',
            self::Stopped => 'gray',
        };
    }
}
