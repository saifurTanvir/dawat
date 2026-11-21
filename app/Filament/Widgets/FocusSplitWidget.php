<?php

namespace App\Filament\Widgets;

use App\Services\Geo\FocusSplit;
use App\Support\AdminFeatures;
use Filament\Widgets\Widget;

/** F92: Badda first, the rest of Dhaka beside it — top of the dashboard. */
class FocusSplitWidget extends Widget
{
    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class);
    }

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.focus-split';

    protected function getViewData(): array
    {
        return ['c' => app(FocusSplit::class)->counts()];
    }
}
