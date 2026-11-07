<?php

namespace App\Filament\Actions;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ReportActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [
            self::close('resolve', 'সমাধান হয়েছে', ReportStatus::Resolved, 'success', 'heroicon-o-check'),
            self::close('dismiss', 'বাতিল', ReportStatus::Dismissed, 'gray', 'heroicon-o-x-mark'),
        ];
    }

    private static function close(string $name, string $label, ReportStatus $status, string $color, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (Report $record) => $record->status === ReportStatus::Open)
            ->action(function (Report $record) use ($status) {
                $record->forceFill([
                    'status' => $status,
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                ])->save();
                Notification::make()->title($status->label())->send();
            });
    }
}
