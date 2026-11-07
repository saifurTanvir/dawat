<?php

namespace App\Filament\Resources\QrCodes;

use App\Filament\Resources\QrCodes\Pages\ManageQrCodes;
use App\Models\QrCode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class QrCodeResource extends Resource
{
    protected static ?string $model = QrCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $modelLabel = 'QR কোড';

    protected static ?string $pluralModelLabel = 'QR কোড';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('area_id')->label('এলাকা (স্ক্যান করলে এই এলাকার খাবার দেখাবে)')
                    ->relationship('area', 'name')->required(),
                TextInput::make('label')->label('কোথায় লাগানো হবে')
                    ->placeholder('যেমন: বায়তুল আমান মসজিদের গেট')->required()->maxLength(120),
                Toggle::make('is_active')->label('চালু')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('label')->label('জায়গা')->searchable()->weight('semibold'),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('code')->label('লিংক')
                    ->formatStateUsing(fn (QrCode $record) => $record->url())
                    ->copyable()
                    ->copyableState(fn (QrCode $record) => $record->url()),
                TextColumn::make('scans')->label('স্ক্যান')->sortable(),
                TextColumn::make('last_scanned_at')->label('শেষ স্ক্যান')->since()->placeholder('—'),
                IconColumn::make('is_active')->label('চালু')->boolean(),
            ])
            ->filters([
                SelectFilter::make('area')->label('এলাকা')->relationship('area', 'name'),
            ])
            ->recordActions([
                Action::make('poster')
                    ->label('পোস্টার প্রিন্ট')
                    ->icon('heroicon-o-printer')
                    ->url(fn (QrCode $record) => route('filament.admin.qr.poster', $record), shouldOpenInNewTab: true),
                ActionGroup::make([
                    Action::make('png')
                        ->label('শুধু QR (PNG)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (QrCode $record) => route('filament.admin.qr.png', $record)),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQrCodes::route('/'),
        ];
    }
}
