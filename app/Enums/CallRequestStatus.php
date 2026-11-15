<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** F66: where a "talk to me" request stands. */
enum CallRequestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case NoAnswer = 'no_answer';
    case Done = 'done';

    /** Bangla label shown in the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'কথা বলা বাকি',
            self::NoAnswer => 'ফোন ধরেননি',
            self::Done => 'কথা হয়েছে',
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
            self::Pending => 'warning',
            self::NoAnswer => 'danger',
            self::Done => 'success',
        };
    }
}
