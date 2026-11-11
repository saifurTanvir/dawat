<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\CsvDownload;
use App\Filament\Support\Links;
use App\Models\Pairing;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** F49: who was paired with whom, and when. */
class PairingsRelationManager extends RelationManager
{
    protected static string $relationship = 'allPairings';

    protected static ?string $title = 'জুটি';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer.user', 'kitchen', 'shahidar']))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('customer.user.name')->label('গ্রাহক')->url(fn (Pairing $p) => CustomerResource::getUrl('view', ['record' => $p->customer_id])),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী')->url(fn (Pairing $p) => Links::kitchen($p->kitchen_id)),
                TextColumn::make('shahidar.name')->label('শাহিদার')->url(fn (Pairing $p) => Links::shahidar($p->shahidar_id)),
                TextColumn::make('started_at')->label('শুরু')->date('j M Y'),
                TextColumn::make('ended_at')->label('শেষ')->date('j M Y')->placeholder('চলছে'),
                TextColumn::make('end_reason')->label('শেষের কারণ')->placeholder('—')->wrap(),
            ])
            ->filters([TernaryFilter::make('ended_at')->label('চলমান')->nullable()->trueLabel('শেষ হয়েছে')->falseLabel('চলছে')])
            ->headerActions([CsvDownload::make('pairings', [
                'গ্রাহক' => fn (Pairing $p) => $p->customer?->user?->name,
                'গৃহিণী' => fn (Pairing $p) => $p->kitchen?->display_name,
                'শাহিদার' => fn (Pairing $p) => $p->shahidar?->name,
                'শুরু' => fn (Pairing $p) => $p->started_at?->toDateString(),
                'শেষ' => fn (Pairing $p) => $p->ended_at?->toDateString(),
                'কারণ' => fn (Pairing $p) => $p->end_reason,
            ], ['customer.user', 'kitchen', 'shahidar'])]);
    }
}
