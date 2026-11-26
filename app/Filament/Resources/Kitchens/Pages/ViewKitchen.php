<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Actions\ActForCookActions;
use App\Filament\Actions\KitchenModerationActions;
use App\Filament\Actions\LoginHelpActions;
use App\Filament\Actions\PinLinkActions;
use App\Filament\Actions\PostForCookActions;
use App\Filament\Actions\RemarkActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Actions\ViewAsAction;
use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewKitchen extends ViewRecord
{
    protected static string $resource = KitchenResource::class;

    protected function getHeaderActions(): array
    {
        // Approve and call come first; the rest wait under "আরও" (QA report).
        $moderation = collect(KitchenModerationActions::make())->keyBy(fn (Action $a) => $a->getName());

        return [
            $moderation['approve'],
            Action::make('callCook')
                ->label('ফোন করুন')
                ->icon(Heroicon::OutlinedPhone)
                ->color('success')
                ->url(fn ($record) => 'tel:'.$record->user->phone)
                ->visible(fn ($record) => filled($record->user?->phone)),
            Action::make('dailyPlates') // F77
                ->label(fn ($record) => $record->daily_plates ? 'দিনে '.($record->daily_plates == 2 ? '২' : '১').' প্লেট' : 'দিনে কত প্লেট?')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color(fn ($record) => $record->daily_plates ? 'gray' : 'warning')
                ->fillForm(fn ($record) => ['daily_plates' => $record->daily_plates])
                ->schema([Select::make('daily_plates')->label('দিনে কত প্লেট দেবেন')->options([1 => '১ প্লেট', 2 => '২ প্লেট'])->required()])
                ->modalSubmitActionLabel('রাখুন')
                ->action(fn ($record, array $data) => $record->update(['daily_plates' => (int) $data['daily_plates']])),
            $moderation['approveContact'],
            $moderation['rejectContact'],
            ActionGroup::make([
                ActForCookActions::menuNotice(),
                ActForCookActions::ready(),
                ActForCookActions::off(),
                PostForCookActions::menus(),
                ActForCookActions::contactHours(),
            ])->label('গৃহিণীর হয়ে')->icon(Heroicon::OutlinedUserCircle)->button()->color('gray'),
            LoginHelpActions::group(fn ($record) => $record->user),
            ActionGroup::make([
                PostForCookActions::postFor(),
                ViewAsAction::make(fn ($record) => $record->user, 'গৃহিণীর অ্যাপ দেখুন'),
                PinLinkActions::make(), // F68
                PinLinkActions::renew(), // F76
                RemarkActions::add(fn ($record) => $record->user),
                $moderation['reject'],
                $moderation['suspend'],
                EditAction::make(),
                RemovalActions::deleteKitchen(),
            ])->label('আরও')->icon(Heroicon::OutlinedEllipsisHorizontal)->button()->color('gray'),
        ];
    }
}
