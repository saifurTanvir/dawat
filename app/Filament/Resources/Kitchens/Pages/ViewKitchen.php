<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Actions\KitchenModerationActions;
use App\Filament\Actions\PostForCookActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKitchen extends ViewRecord
{
    protected static string $resource = KitchenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PostForCookActions::postFor(),
            PostForCookActions::menus(),
            ...KitchenModerationActions::make(),
            EditAction::make(),
            RemovalActions::deleteKitchen(),
        ];
    }
}
