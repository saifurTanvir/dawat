<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Actions\ActForCookActions;
use App\Filament\Actions\KitchenModerationActions;
use App\Filament\Actions\LoginHelpActions;
use App\Filament\Actions\PostForCookActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewKitchen extends ViewRecord
{
    protected static string $resource = KitchenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PostForCookActions::postFor(),
            LoginHelpActions::group(fn ($record) => $record->user),
            ActionGroup::make([
                ActForCookActions::ready(),
                ActForCookActions::off(),
                PostForCookActions::menus(),
                ActForCookActions::contactHours(),
            ])->label('গৃহিণীর হয়ে')->icon(Heroicon::OutlinedUserCircle)->button()->color('gray'),
            ...KitchenModerationActions::make(),
            EditAction::make(),
            RemovalActions::deleteKitchen(),
        ];
    }
}
