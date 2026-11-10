<?php

namespace App\Filament\Resources\Kitchens\Tables;

use App\Enums\KitchenStatus;
use App\Filament\Actions\KitchenModerationActions;
use App\Filament\Actions\RemovalActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KitchensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('রান্নাঘর')->searchable()->weight('semibold'),
                TextColumn::make('user.phone')->label('ফোন')->searchable()->copyable(),
                TextColumn::make('area.name')->label('এলাকা')->placeholder('—'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('pending_contact_phone')
                    ->label('নতুন নম্বর')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state, $record) => "{$record->pending_contact_name}: {$state}".
                        ($record->pending_contact_method === 'otp' ? ' (কোডের অপেক্ষায়)' : ' (অনুমোদন দরকার)'))
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('location')
                    ->label('লোকেশন')
                    ->state(fn ($record) => $record->location !== null)
                    ->boolean(),
                TextColumn::make('posts_count')->label('পোস্ট')->counts('posts')->sortable(),
                TextColumn::make('created_at')->label('নিবন্ধন')->since()->sortable(),
                TextColumn::make('approved_at')->label('অনুমোদন')->dateTime('d M Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(KitchenStatus::class),
                SelectFilter::make('area')->label('এলাকা')->relationship('area', 'name'),
                Filter::make('contact_waiting')
                    ->label('নম্বর অনুমোদনের অপেক্ষায়')
                    ->query(fn ($query) => $query->where('pending_contact_method', 'admin')),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ...KitchenModerationActions::make(),
                    EditAction::make(),
                    RemovalActions::deleteKitchen(),
                ]),
            ]);
    }
}
