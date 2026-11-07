<?php

namespace App\Enums;

enum ContactType: string
{
    case Call = 'call';
    case Message = 'message';
    case Comment = 'comment';
    case Share = 'share';

    /** Bangla label shown in the app and the admin panel. */
    public function label(): string
    {
        return match ($this) {
            self::Call => 'কল',
            self::Message => 'মেসেজ',
            self::Comment => 'কমেন্ট',
            self::Share => 'শেয়ার',
        };
    }

    /** @return array<string, string> value => label, for select inputs. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
