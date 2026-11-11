<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\CsvDownload;
use App\Filament\Support\Links;
use App\Models\KitchenAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F49: what this person did for cooks (F46). */
class KitchenActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'kitchenActions';

    protected static ?string $title = 'গৃহিণীর হয়ে করা কাজ';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('kitchen'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('কবে')->dateTime('j M Y, g:i a', config('app.display_timezone')),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী')->url(fn (KitchenAction $a) => Links::kitchen($a->kitchen_id)),
                TextColumn::make('details')->label('কাজ')->wrap(),
            ])
            ->headerActions([CsvDownload::make('acted-for-cooks', [
                'কবে' => fn (KitchenAction $a) => $a->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
                'গৃহিণী' => fn (KitchenAction $a) => $a->kitchen?->display_name,
                'কাজ' => fn (KitchenAction $a) => $a->details,
            ], ['kitchen'])]);
    }
}
