<?php

namespace App\Support\Geo;

/** Moves a point a random distance in a random direction (see config/dawat.php). */
final class LocationFuzzer
{
    public static function fuzz(GeoPoint $home): GeoPoint
    {
        $min = (int) config('dawat.location_fuzz.min_m');
        $max = max($min, (int) config('dawat.location_fuzz.max_m'));

        if ($max === 0) {
            return $home;
        }

        $distance = random_int($min, $max);
        $bearing = deg2rad(random_int(0, 359_999) / 1000);

        $north = $distance * cos($bearing);
        $east = $distance * sin($bearing);

        return GeoPoint::make(
            $home->lat + $north / 111_320,
            $home->lng + $east / (111_320 * cos(deg2rad($home->lat))),
        );
    }
}
