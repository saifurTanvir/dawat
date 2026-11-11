<?php

namespace App\Filament\Widgets;

use App\Services\Posts\PlateSplit;
use Filament\Widgets\Widget;

/** F44: today, cook by cook and meal by meal — posted or not, and where the plates went. */
class TodayPlates extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.today-plates';

    protected function getViewData(): array
    {
        return ['rows' => app(PlateSplit::class)->today()];
    }
}
