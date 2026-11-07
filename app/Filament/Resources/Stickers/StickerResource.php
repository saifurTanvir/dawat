<?php

namespace App\Filament\Resources\Stickers;

use App\Filament\Resources\Stickers\Pages\ManageStickers;
use App\Models\Sticker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** F27: pre-printed QR stickers for boxes. */
class StickerResource extends Resource
{
    protected static ?string $model = Sticker::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'বক্স স্টিকার';

    protected static ?string $pluralModelLabel = 'বক্স স্টিকার';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label('স্টিকার')->state(fn (Sticker $record) => $record->displayCode())->searchable(query: fn ($query, string $search) => $query->where('code', 'like', '%'.strtoupper(str_replace(' ', '', $search)).'%')),
                TextColumn::make('batch')->label('ব্যাচ'),
                TextColumn::make('order.seq')->label('বক্স')->state(fn (Sticker $record) => $record->order?->code())->placeholder('ফাঁকা'),
                TextColumn::make('bound_at')->label('লাগানো')->since()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('used')->label('ব্যবহৃত')->queries(
                    true: fn ($query) => $query->whereNotNull('order_id'),
                    false: fn ($query) => $query->whereNull('order_id'),
                ),
            ])
            ->recordActions([
                Action::make('print')->label('এই ব্যাচ প্রিন্ট')->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Sticker $record) => route('filament.admin.stickers.sheet', $record->batch))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStickers::route('/'),
        ];
    }
}
