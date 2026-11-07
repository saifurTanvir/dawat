<?php

namespace App\Filament\Actions;

use App\Enums\KitchenStatus;
use App\Models\Kitchen;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/** Approve / reject / suspend, shared by the kitchen table and view page. */
class KitchenModerationActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [
            Action::make('approve')
                ->label('অনুমোদন দিন')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('অনুমোদনের আগে গৃহিণীর সাথে ফোনে কথা বলে নিশ্চিত হয়েছেন তো?')
                ->visible(fn (Kitchen $record) => $record->status !== KitchenStatus::Approved)
                ->action(function (Kitchen $record) {
                    $record->approve(auth()->user());
                    Notification::make()->title('অনুমোদন দেওয়া হয়েছে')->success()->send();
                }),

            Action::make('reject')
                ->label('বাতিল করুন')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('কারণ (গৃহিণী দেখতে পাবেন)')->required()->maxLength(255),
                ])
                ->visible(fn (Kitchen $record) => $record->status === KitchenStatus::Pending)
                ->action(function (Kitchen $record, array $data) {
                    $record->reject(auth()->user(), $data['reason']);
                    Notification::make()->title('বাতিল করা হয়েছে')->send();
                }),

            Action::make('suspend')
                ->label('সাময়িক বন্ধ')
                ->icon('heroicon-o-pause-circle')
                ->color('gray')
                ->schema([
                    Textarea::make('reason')->label('কারণ (গৃহিণী দেখতে পাবেন)')->required()->maxLength(255),
                ])
                ->visible(fn (Kitchen $record) => $record->status === KitchenStatus::Approved)
                ->action(function (Kitchen $record, array $data) {
                    $record->suspend(auth()->user(), $data['reason']);
                    Notification::make()->title('সাময়িক বন্ধ করা হয়েছে')->send();
                }),
        ];
    }
}
