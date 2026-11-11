<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Actions\PublishPost;
use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Filament\Actions\PostModerationActions;
use App\Filament\Actions\RemovalActions;
use App\Services\Feed\PostReach;
use App\Services\Posts\PlateSplit;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('reachAreas')->with(['orders', 'postedBy']))
            ->columns([
                TextColumn::make('title')->label('খাবার')->searchable()->weight('semibold')->wrap(),
                TextColumn::make('kitchen.display_name')->label('রান্নাঘর')->searchable(),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('price_paisa')->label('দাম')->formatStateUsing(fn (int $state) => '৳'.number_format($state / 100)),
                TextColumn::make('plates_left')->label('প্লেটের ভাগ')
                    ->formatStateUsing(fn ($record) => PlateSplit::line(app(PlateSplit::class)->forPost($record)).' / মোট '.$record->plates_total)
                    ->wrap(),
                TextColumn::make('meal_slot')->label('বেলা')->badge()->color('gray'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('source')->label('পোস্ট দিয়েছেন')->badge()
                    ->formatStateUsing(fn (?string $state, $record) => (PublishPost::SOURCES[$state] ?? 'গৃহিণী নিজে').($record->postedBy?->name && $state !== 'cook' ? ' · '.$record->postedBy->name : ''))
                    ->color(fn (?string $state) => ($state ?? 'cook') === 'cook' ? 'gray' : 'info'),
                TextColumn::make('reach_m')->label('পরিধি')
                    ->state(fn ($record) => PostReach::summary($record->reach_m, $record->reach_areas_count ?? 0))
                    ->badge()->color(fn ($record) => $record->boosted ? 'success' : 'gray'),
                TextColumn::make('views_count')->label('দেখেছে')->counts('views')->sortable(),
                TextColumn::make('contact_events_count')->label('যোগাযোগ')->counts('contactEvents')->sortable(),
                TextColumn::make('available_until')->label('পাওয়া যাবে')->dateTime('d M, h:i A')->sortable(),
                TextColumn::make('created_at')->label('পোস্ট')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(PostStatus::class),
                SelectFilter::make('meal_slot')->label('বেলা')->options(MealSlot::class),
                SelectFilter::make('area')->label('এলাকা')->relationship('area', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([...PostModerationActions::make(), RemovalActions::deletePost()]),
            ]);
    }
}
