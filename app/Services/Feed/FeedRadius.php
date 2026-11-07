<?php

namespace App\Services\Feed;

/** The distance chips on the feed. Values are straight-line metres. */
enum FeedRadius: string
{
    case Walk = 'walk';     // "৫ মিনিট হাঁটা"
    case OneKm = '1km';
    case ThreeKm = '3km';
    case Area = 'all';      // "পুরো এলাকা"

    public function metres(): int
    {
        return match ($this) {
            // 5 minutes at ~80 m/min is 400 m of walking; Dhaka lanes add ~30 %.
            self::Walk => 300,
            self::OneKm => 1000,
            self::ThreeKm => 3000,
            self::Area => 8000,
        };
    }
}
