<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Kitchens\KitchenResource;
use App\Models\Kitchen;
use App\Support\DhakaTime;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** F23: which cooks buyers reach most this week. */
class TopKitchens extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'এই সপ্তাহের জনপ্রিয় রান্নাঘর';

    public function table(Table $table): Table
    {
        $since = DhakaTime::startOfDay(6);

        return $table
            ->query(fn (): Builder => Kitchen::query()
                ->where('status', 'approved')
                ->with('area')
                ->withCount([
                    'posts as week_posts' => fn ($q) => $q->where('posts.created_at', '>=', $since),
                    'contactEvents as week_contacts' => fn ($q) => $q->where('contact_events.created_at', '>=', $since),
                ])
                ->having('week_posts', '>', 0)
                ->orderByDesc('week_contacts'))
            ->paginated([5])
            ->columns([
                TextColumn::make('display_name')->label('রান্নাঘর')->weight('semibold'),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('week_posts')->label('পোস্ট'),
                TextColumn::make('week_contacts')->label('যোগাযোগ'),
            ])
            ->recordUrl(fn (Kitchen $record) => KitchenResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('এই সপ্তাহে এখনো কোনো পোস্ট নেই');
    }
}
