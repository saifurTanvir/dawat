<?php

namespace App\Filament\Pages;

use App\Filament\Actions\QuickAddActions;
use App\Models\OpsHeartbeat;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/** F58: the dashboard opens with "add a customer / cook / shahidar". */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        // F69: the quick-add buttons belong to "নতুন মানুষ যোগ".
        return auth()->user()?->canUseFeature('import') ? QuickAddActions::all() : [];
    }

    /** F78: admins see whether the reply loops are working, above the buttons. */
    public function getSubheading(): string|Htmlable|null
    {
        if (! auth()->user()?->isAdmin()) {
            return null;
        }

        return view('filament.pages.partials.ops-summary', ['channels' => OpsHeartbeat::summary()]);
    }
}
