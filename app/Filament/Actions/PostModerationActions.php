<?php

namespace App\Filament\Actions;

use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PostModerationActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [
            Action::make('hide')
                ->label('লুকান')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('পোস্টটি আর কোনো ক্রেতা দেখতে পাবেন না।')
                ->visible(fn (Post $record) => $record->status !== PostStatus::Hidden)
                ->action(function (Post $record) {
                    $record->update(['status' => PostStatus::Hidden]);
                    Notification::make()->title('পোস্ট লুকানো হয়েছে')->send();
                }),

            Action::make('unhide')
                ->label('আবার দেখান')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Post $record) => $record->status === PostStatus::Hidden)
                ->action(function (Post $record) {
                    $status = $record->plates_left > 0 && $record->available_until->isFuture()
                        ? PostStatus::Live
                        : PostStatus::Expired;
                    $record->update(['status' => $status]);
                    Notification::make()->title('পোস্ট আবার দেখানো হচ্ছে')->success()->send();
                }),
        ];
    }
}
