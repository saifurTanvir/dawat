<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommentStatus: string implements HasColor, HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Visible => 'success',
            self::Hidden => 'danger',
        };
    }
}
