<?php

namespace App\Filament\Pages;

use App\Filament\Actions\QuickAddActions;
use Filament\Pages\Dashboard as BaseDashboard;

/** F58: the dashboard opens with "add a customer / cook / shahidar". */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        // F69: the quick-add buttons belong to "নতুন মানুষ যোগ".
        return auth()->user()?->canUseFeature('import') ? QuickAddActions::all() : [];
    }
}
