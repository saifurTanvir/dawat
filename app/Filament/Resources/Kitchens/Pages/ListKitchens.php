<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Actions\QuickAddActions;
use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Resources\Pages\ListRecords;

class ListKitchens extends ListRecords
{
    protected static string $resource = KitchenResource::class;

    /** F58: admins can add a cook who phoned in. */
    protected function getHeaderActions(): array
    {
        return [QuickAddActions::cook()];
    }
}
