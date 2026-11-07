<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** "Today" for Dawat means today in Dhaka, while the database stores UTC. */
final class DhakaTime
{
    public const TZ = 'Asia/Dhaka';

    /** Start of the Dhaka day $daysAgo days back, in UTC for queries. */
    public static function startOfDay(int $daysAgo = 0): Carbon
    {
        return now(self::TZ)->subDays($daysAgo)->startOfDay()->utc();
    }

    /** SQL expression giving the Dhaka calendar date of a UTC timestamp column. */
    public static function dateSql(string $column): string
    {
        // Bangladesh has no daylight saving, so a fixed offset is exact.
        return "DATE(CONVERT_TZ({$column}, '+00:00', '+06:00'))";
    }
}
