<?php

namespace App\Filament\RelationManagers;

use App\Models\Impersonation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F51: who opened this person's app, when, and what they did in it. */
class ImpersonationsRelationManager extends RelationManager
{
    protected static string $relationship = 'impersonations';

    protected static ?string $title = 'অন্য কেউ অ্যাপ খুলেছেন';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['actor', 'actions'])->whereNotNull('claimed_at'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('claimed_at')->label('কবে')->dateTime('j M Y, g:i a', config('app.display_timezone')),
                TextColumn::make('actor_role')->label('কে')->state(fn (Impersonation $i) => $i->actorLabel()),
                TextColumn::make('ended_at')->label('শেষ')->state(fn (Impersonation $i) => ($i->ended_at ?? ($i->ends_at->isPast() ? $i->ends_at : null))?->timezone(config('app.display_timezone'))->format('g:i a'))->placeholder('চলছে'),
                TextColumn::make('actions_list')->label('যা করেছেন')->wrap()->placeholder('শুধু দেখেছেন')
                    ->state(fn (Impersonation $i) => $i->actions->where('status', '<', 400)->map->label()->unique()->implode(', ') ?: null),
            ]);
    }
}
