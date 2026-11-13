<?php

namespace App\Support\Geo;

/**
 * F55: reads a point from what an admin pastes — "23.78, 90.42", a Google Maps
 * link (…/@23.78,90.42,17z or ?q=23.78,90.42) or a WhatsApp location link.
 */
final class MapLink
{
    public static function parse(?string $text): ?GeoPoint
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        $text = strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);

        if (! preg_match('/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{2,3}\.\d{3,})/', urldecode($text), $m)) {
            return null;
        }
        [$lat, $lng] = [(float) $m[1], (float) $m[2]];
        if ($lat < Bangladesh::MIN_LAT || $lat > Bangladesh::MAX_LAT || $lng < Bangladesh::MIN_LNG || $lng > Bangladesh::MAX_LNG) {
            return null;
        }

        return GeoPoint::make($lat, $lng);
    }

    public static function googleMaps(GeoPoint $point): string
    {
        return 'https://www.google.com/maps?q='.$point->lat.','.$point->lng;
    }
}
