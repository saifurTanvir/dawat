<?php

namespace App\Filament\Resources\Kitchens\Tables;

use App\Enums\KitchenStatus;
use App\Filament\Actions\KitchenModerationActions;
use App\Filament\Actions\PostForCookActions;
use App\Filament\Actions\RemovalActions;
use App\Models\Order;
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
                // F55: one cook, one regular customer; more than one is from before the rule.
                TextColumn::make('pairings_count')->label('নিয়মিত গ্রাহক')->counts('pairings')->sortable()
                    ->badge()
                    ->formatStateUsing(fn (int $state) => match (true) {
                        $state === 0 => 'খালি',
                        $state === 1 => '১ জন',
                        default => Order::banglaDigits($state).' জন — বেশি',
                    })
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'success',
                        $state === 1 => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('posts_count')->label('পোস্ট')->counts('posts')->sortable(),
                TextColumn::make('rating_avg')->label('রেটিং')->sortable()
                    ->formatStateUsing(fn ($state, $record) => $record->rating_count ? '★ '.number_format((float) $state, 1).' ('.$record->rating_count.')' : '—')->placeholder('—'),
                TextColumn::make('created_at')->label('নিবন্ধন')->since()->sortable(),
                TextColumn::make('approved_at')->label('অনুমোদন')->dateTime('d M Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(KitchenStatus::class),
                SelectFilter::make('area')->label('এলাকা')->relationship('area', 'name'),
                Filter::make('regular_free')->label('নিয়মিত গ্রাহকের জন্য খালি')
                    ->query(fn ($query) => $query->where('status', KitchenStatus::Approved)->whereDoesntHave('pairings')),
                Filter::make('regular_over')->label('একাধিক নিয়মিত গ্রাহক (সরাতে হবে)')
                    ->query(fn ($query) => $query->has('pairings', '>', 1)),
                Filter::make('contact_waiting')
                    ->label('নম্বর অনুমোদনের অপেক্ষায়')
                    ->query(fn ($query) => $query->where('pending_contact_method', 'admin')),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    PostForCookActions::postFor(),
                    PostForCookActions::menus(),
                    ...KitchenModerationActions::make(),
                    EditAction::make(),
                    RemovalActions::deleteKitchen(),
                ]),
            ]);
    }
}
