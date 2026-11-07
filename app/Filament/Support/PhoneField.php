<?php

namespace App\Filament\Support;

use App\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\TextInput;

/** A Bangladeshi mobile number field that also accepts Bangla digits and +88. */
final class PhoneField
{
    public static function make(string $name = 'phone'): TextInput
    {
        return TextInput::make($name)
            ->label('মোবাইল নম্বর')
            ->required()
            ->inputMode('tel')
            ->placeholder('01XXXXXXXXX')
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                if (! PhoneNumber::normalize((string) $value)) {
                    $fail('সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।');
                }
            });
    }
}
