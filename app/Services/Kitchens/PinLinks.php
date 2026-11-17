<?php

namespace App\Services\Kitchens;

use App\Models\PinLink;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use Illuminate\Validation\ValidationException;

/**
 * F68: a cook's pin from her link. GPS from home must be accurate; a pin far
 * from the area she signed up in waits for an admin instead of going live.
 */
final class PinLinks
{
    public const MAX_GPS_ACCURACY_M = 100;

    /** How far past her area's own radius a pin may fall before an admin checks it. */
    public const SLACK_M = 1500;

    /** @return 'saved'|'review' */
    public function submit(PinLink $link, GeoPoint $at, string $mode, ?int $accuracy): string
    {
        if ($link->isExpired()) {
            throw ValidationException::withMessages(['link' => 'লিংকের মেয়াদ শেষ। দাওয়াত থেকে নতুন লিংক নিন।']);
        }
        if ($mode === 'home' && ($accuracy === null || $accuracy > self::MAX_GPS_ACCURACY_M)) {
            throw ValidationException::withMessages(['accuracy' => 'ফোনের লোকেশন ঠিকমতো পাওয়া যায়নি। জানালার কাছে গিয়ে আবার চাপুন, বা ম্যাপে নিজে পিন দিন।']);
        }

        $kitchen = $link->kitchen()->with('area')->firstOrFail();
        $distance = $kitchen->area?->center ? (int) round($at->distanceTo($kitchen->area->center)) : null;
        $flagged = $distance !== null && $distance > (int) $kitchen->area->radius_m + self::SLACK_M;

        $link->forceFill([
            'submitted_location' => $at,
            'mode' => $mode,
            'accuracy_m' => $accuracy,
            'distance_m' => $distance,
            'flagged' => $flagged,
            'submitted_at' => now(),
            'review' => $flagged ? null : 'accepted',
            'reviewed_at' => null,
            'reviewed_by' => null,
        ])->save();

        if ($flagged) {
            return 'review';
        }
        $kitchen->location = $at;
        $kitchen->save();

        return 'saved';
    }

    public function accept(PinLink $link, User $admin): void
    {
        $kitchen = $link->kitchen;
        $kitchen->location = $link->submitted_location;
        $kitchen->save();
        $link->forceFill(['review' => 'accepted', 'reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();
    }

    public function reject(PinLink $link, User $admin): void
    {
        $link->forceFill(['review' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();
    }
}
