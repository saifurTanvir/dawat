<?php

namespace App\Filament\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class UserBlockActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [
            Action::make('block')
                ->label('বন্ধ করুন')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('এই নম্বর দিয়ে আর লগইন করা যাবে না, আর বর্তমান লগইনও বাতিল হবে।')
                ->visible(fn (User $record) => ! $record->isBlocked() && $record->id !== auth()->id())
                ->action(function (User $record) {
                    $record->update(['status' => UserStatus::Blocked]);
                    $record->tokens()->delete();
                    // A blocked cook must stop receiving calls at once.
                    if ($record->kitchen?->isApproved()) {
                        $record->kitchen->suspend(auth()->user(), 'ইউজার বন্ধ করা হয়েছে');
                    }
                    Notification::make()->title('ইউজার বন্ধ করা হয়েছে')->send();
                }),

            Action::make('unblock')
                ->label('আবার চালু করুন')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (User $record) => $record->isBlocked())
                ->action(function (User $record) {
                    $record->update(['status' => UserStatus::Active]);
                    Notification::make()->title('ইউজার আবার চালু')->success()->send();
                }),

            // F29: lifted by hand after repeated no-shows paused "নিতে চাই".
            Action::make('allowOrders')
                ->label('"নিতে চাই" আবার চালু')
                ->icon('heroicon-o-shopping-bag')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (User $record) => $record->order_blocked_until?->isFuture() ?? false)
                ->action(function (User $record) {
                    $record->forceFill(['order_blocked_until' => null])->save();
                    Notification::make()->title('অর্ডার আবার চালু')->success()->send();
                }),
        ];
    }
}
