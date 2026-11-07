<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKitchen extends EditRecord
{
    protected static string $resource = KitchenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
