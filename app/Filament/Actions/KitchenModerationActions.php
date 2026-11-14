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
                // F57: a cook who signed up without a code is verified on the call.
                ->schema(fn (Kitchen $record) => LoginHelpActions::callCheckbox($record->user))
                ->action(function (Kitchen $record, array $data) {
                    LoginHelpActions::confirmCall($record->user, $data);
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

            // F5: a cook asked to let buyers reach another number and chose admin approval.
            Action::make('approveContact')
                ->label('নম্বর অনুমোদন')
                ->icon('heroicon-o-phone')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('যোগাযোগের নম্বর অনুমোদন')
                ->modalDescription(fn (Kitchen $record) => "{$record->pending_contact_name} ({$record->pending_contact_phone}) — "
                    .'এই নম্বরে ফোন করে নিশ্চিত হয়েছেন তো যে তিনি রাজি?')
                ->visible(fn (Kitchen $record) => $record->pending_contact_method === 'admin')
                ->action(function (Kitchen $record) {
                    $record->confirmPendingContact('admin');
                    Notification::make()->title('নম্বর অনুমোদন দেওয়া হয়েছে')->success()->send();
                }),

            Action::make('rejectContact')
                ->label('নম্বর বাতিল')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (Kitchen $record) => $record->hasPendingContact())
                ->action(function (Kitchen $record) {
                    $record->clearPendingContact();
                    Notification::make()->title('নম্বরের অনুরোধ বাতিল')->send();
                }),
        ];
    }
}
