<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Enums\KitchenStatus;
use App\Filament\Actions\QuickAddActions;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Models\Kitchen;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListKitchens extends ListRecords
{
    protected static string $resource = KitchenResource::class;

    /** F58: admins can add a cook who phoned in. */
    protected function getHeaderActions(): array
    {
        return [
            QuickAddActions::cook(),
            Action::make('defaultPlates')
                ->visible(fn () => (bool) auth()->user()?->isAdmin())
                ->label(fn () => 'প্লেট অজানা '.Kitchen::query()->whereNull('daily_plates')->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->count().' জনকে ১ প্লেট')
                ->icon(Heroicon::OutlinedSquare2Stack)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('যেসব রান্নাঘরের "দিনে কত প্লেট" জানা নেই, সবার দিনে ১ প্লেট বসবে। পরে কেউ ২ বললে আলাদা করে বদলানো যাবে।')
                ->action(function () {
                    $n = Kitchen::query()->whereNull('daily_plates')->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->update(['daily_plates' => 1]);
                    Notification::make()->title("{$n}টা রান্নাঘরে দিনে ১ প্লেট বসানো হলো")->success()->send();
                }),
        ];
    }
}
