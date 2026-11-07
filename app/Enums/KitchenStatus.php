<?php

namespace App\Enums;

enum KitchenStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'অনুমোদনের অপেক্ষায়',
            self::Approved => 'অনুমোদিত',
            self::Rejected => 'বাতিল',
            self::Suspended => 'সাময়িক বন্ধ',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
