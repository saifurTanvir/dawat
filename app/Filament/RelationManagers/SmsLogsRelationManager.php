<?php

namespace App\Filament\RelationManagers;

use App\Models\SmsLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F50: every code sent to this number, and what happened. */
class SmsLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'smsLogs';

    protected static ?string $title = 'SMS ও কোড';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('sender'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('কবে')->dateTime('j M, g:i a', config('app.display_timezone')),
                TextColumn::make('purpose')->label('কী কাজে'),
                TextColumn::make('status')->label('ফল')->badge()
                    ->formatStateUsing(fn (string $state) => SmsLog::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success', 'failed', 'reported' => 'danger', default => 'info'
                    }),
                TextColumn::make('error')->label('কারণ')->placeholder('—')->wrap(),
                TextColumn::make('sender.name')->label('কে পাঠালেন')->placeholder('সিস্টেম'),
            ]);
    }
}
