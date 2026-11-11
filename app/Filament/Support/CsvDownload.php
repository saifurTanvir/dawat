<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F49: download what a history table shows (its filters applied) as a CSV
 * that Excel opens with Bangla intact.
 */
final class CsvDownload
{
    /** @param  array<string, Closure>  $columns  header => fn ($record) => value */
    public static function make(string $file, array $columns, array $with = []): Action
    {
        return Action::make('csv')
            ->label('CSV নামান')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function ($livewire) use ($file, $columns, $with): StreamedResponse {
                $rows = $livewire->getFilteredSortedTableQuery()->with($with)->limit(20000)->get();

                return response()->streamDownload(function () use ($rows, $columns) {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_keys($columns));
                    foreach ($rows as $row) {
                        fputcsv($out, array_map(fn (Closure $c) => (string) ($c($row) ?? ''), array_values($columns)));
                    }
                    fclose($out);
                }, 'dawat-'.$file.'-'.now(config('app.display_timezone'))->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
