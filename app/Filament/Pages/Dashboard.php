<?php

namespace App\Filament\Pages;

use App\Filament\Actions\QuickAddActions;
use Filament\Pages\Dashboard as BaseDashboard;

/** F58: the dashboard opens with "add a customer / cook / shahidar". */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return QuickAddActions::all();
    }
}
