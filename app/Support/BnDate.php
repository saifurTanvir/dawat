<?php

namespace App\Support;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Dates and money the Bangla way, for admin pages: "২৬ নভে", "২৬ নভে, রাত ৮:০৩", "৳১,৬০০". */
final class BnDate
{
    private const MONTHS = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];

    public static function day(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '—';
        }
        $d = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return Order::banglaDigits($d->day).' '.self::MONTHS[$d->month - 1];
    }

    public static function dayTime(CarbonInterface|string|null $date): string
    {
        if (! $date) {
            return '—';
        }
        $d = ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->copy()->timezone(DhakaTime::TZ);

        return self::day($d).', '.ContactHours::clock($d->format('H:i'));
    }

    public static function taka(int $paisa): string
    {
        return ($paisa < 0 ? '-' : '').'৳'.Order::banglaDigits(number_format(intdiv(abs($paisa), 100)));
    }
}
