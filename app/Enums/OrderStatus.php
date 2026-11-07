<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Booked = 'booked';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'বুক হয়েছে',
            self::PickedUp => 'পিকআপ হয়েছে',
            self::Delivered => 'পৌঁছেছে',
            self::Cancelled => 'বাতিল',
            self::Failed => 'পৌঁছানো যায়নি',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Booked => 'info',
            self::PickedUp => 'primary',
            self::Delivered => 'success',
            self::Cancelled, self::Failed => 'warning',
        };
    }

    /** Still on its way: counts against the cook's plates. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Booked, self::PickedUp], true);
    }
}
