<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** F53: where an application to work as a shahidar stands. */
enum ShahidarApplicationStatus: string implements HasColor, HasLabel
{
    /** Just sent; nobody has called yet. */
    case New = 'new';
    /** An admin has talked to them. */
    case Contacted = 'contacted';
    /** Now a shahidar. */
    case Onboarded = 'onboarded';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'নতুন',
            self::Contacted => 'কথা হয়েছে',
            self::Onboarded => 'শাহিদার হয়েছেন',
            self::Rejected => 'বাদ',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Onboarded => 'success',
            self::Rejected => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted], true);
    }
}
