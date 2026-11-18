<?php

namespace App\Filament\Pages;

use App\Filament\PageWidgets\DeliveryQualityTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** F67: which shahidars skip the sticker or hand over without any check. */
class DeliveryQuality extends Page
{
    protected string $view = 'filament.pages.delivery-quality';

    protected static ?string $slug = 'delivery-quality';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $navigationLabel = 'ডেলিভারির হিসাব';

    protected static ?string $title = 'ডেলিভারির হিসাব';

    protected static ?int $navigationSort = 45;

    protected function getHeaderWidgets(): array
    {
        return [DeliveryQualityTable::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
