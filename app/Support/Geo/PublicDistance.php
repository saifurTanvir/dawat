<?php

namespace App\Support\Geo;

/**
 * Distances shown to buyers. Rounded up to 50 m and never below 100 m, so
 * nobody can work out a cook's house by asking from a few nearby spots.
 */
final class PublicDistance
{
    public const STEP = 50;

    public const MINIMUM = 100;

    /** Dhaka lanes are not straight lines. */
    public const DETOUR = 1.3;

    public const WALK_METRES_PER_MINUTE = 80;

    public static function round(float $metres): int
    {
        return max(self::MINIMUM, (int) (ceil($metres / self::STEP) * self::STEP));
    }

    public static function walkMinutes(float $metres): int
    {
        return max(1, (int) ceil(self::round($metres) * self::DETOUR / self::WALK_METRES_PER_MINUTE));
    }
}
