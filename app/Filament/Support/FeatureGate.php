<?php

namespace App\Filament\Support;

use App\Support\AdminFeatures;

/** F69: a panel resource or page that only admins and staff with its feature open. */
trait FeatureGate
{
    public static function canAccess(): bool
    {
        return AdminFeatures::allows(static::class);
    }
}
