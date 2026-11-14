<?php

namespace App\Filament\Support;

use App\Support\Geo\Geocoder;
use Closure;
use Filament\Forms\Components\ViewField;

/**
 * F62: a home location in admin forms. Paste a Google Maps / WhatsApp link
 * (short links too) or "lat, lng", tap "এখানকার লোকেশন", or type an address
 * and pick a result; a small map shows the pin, which can be dragged.
 * The state is the text; Geocoder::point() reads it on save.
 */
final class LocationField
{
    public static function make(string $name = 'where', string $label = 'বাসার লোকেশন (ইচ্ছা হলে)', bool $required = false): ViewField
    {
        return ViewField::make($name)
            ->label($label)
            ->view('filament.forms.location-input')
            ->required($required)
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && ! app(Geocoder::class)->point((string) $value)) {
                    $fail('এখান থেকে লোকেশন পড়া গেল না। ম্যাপের লিংক দিন, "এখানকার লোকেশন" চাপুন, বা ঠিকানা খুঁজে একটা বেছে নিন।');
                }
            });
    }
}
