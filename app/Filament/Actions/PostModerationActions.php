<?php

namespace App\Filament\Actions;

use App\Enums\PostStatus;
use App\Filament\Support\ReachFields;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PostModerationActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [
            // F35: show this post farther away or in other areas.
            Action::make('reach')
                ->label('পরিধি বদলান')
                ->icon('heroicon-o-map')
                ->color('primary')
                ->modalHeading('এই পোস্ট কোথায় দেখাবে')
                ->modalDescription('বাড়ালে বেশি মানুষ দেখবেন। শুধু এই পোস্টে লাগবে; রান্নাঘরের সব পোস্টে চাইলে রান্নাঘরের পাতা থেকে বদলান।')
                ->fillForm(fn (Post $record) => ['reach_m' => $record->reach_m, 'reach_area_ids' => $record->reachAreas()->pluck('areas.id')->all()])
                ->schema(ReachFields::make())
                ->action(function (Post $record, array $data) {
                    $record->setReach($data['reach_m'] ? (int) $data['reach_m'] : null, array_map('intval', $data['reach_area_ids'] ?? []));
                    Notification::make()->title('পরিধি বদলানো হয়েছে')->success()->send();
                }),

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
