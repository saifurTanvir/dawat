<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\CsvDownload;
use App\Models\Settlement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F49: money settled — Dawat paying a cook, or a shahidar handing in cash. */
class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';

    protected static ?string $title = 'টাকার হিসাব';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $tk = fn (int $p) => '৳'.number_format(intdiv($p, 100));

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('settler'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('কবে')->dateTime('j M Y, g:i a', config('app.display_timezone')),
                TextColumn::make('period')->label('সময়')->state(fn (Settlement $s) => $s->from_date ? $s->from_date->format('j M').' – '.$s->to_date?->format('j M') : null)->placeholder('—'),
                TextColumn::make('orders_count')->label('বক্স'),
                TextColumn::make('gross_paisa')->label('মোট')->formatStateUsing(fn ($state) => $tk((int) $state)),
                TextColumn::make('commission_paisa')->label('কমিশন')->formatStateUsing(fn ($state) => $tk((int) $state)),
                TextColumn::make('amount_paisa')->label('টাকা')->formatStateUsing(fn ($state) => $tk((int) $state))->weight('bold'),
                TextColumn::make('settler.name')->label('কে করেছেন')->placeholder('—'),
                TextColumn::make('note')->label('নোট')->placeholder('—')->wrap(),
            ])
            ->headerActions([CsvDownload::make('settlements', [
                'কবে' => fn (Settlement $s) => $s->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
                'বক্স' => fn (Settlement $s) => $s->orders_count,
                'মোট' => fn (Settlement $s) => intdiv((int) $s->gross_paisa, 100),
                'কমিশন' => fn (Settlement $s) => intdiv((int) $s->commission_paisa, 100),
                'টাকা' => fn (Settlement $s) => intdiv((int) $s->amount_paisa, 100),
                'নোট' => fn (Settlement $s) => $s->note,
            ])]);
    }
}
