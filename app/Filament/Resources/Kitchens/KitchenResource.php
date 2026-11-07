<?php

namespace App\Filament\Resources\Kitchens;

use App\Enums\KitchenStatus;
use App\Filament\Resources\Kitchens\Pages\EditKitchen;
use App\Filament\Resources\Kitchens\Pages\ListKitchens;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Kitchens\Schemas\KitchenForm;
use App\Filament\Resources\Kitchens\Schemas\KitchenInfolist;
use App\Filament\Resources\Kitchens\Tables\KitchensTable;
use App\Models\Kitchen;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KitchenResource extends Resource
{
    protected static ?string $model = Kitchen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'রাঁধুনি ও খাবার';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'রান্নাঘর';

    protected static ?string $pluralModelLabel = 'রান্নাঘর';

    protected static ?string $recordTitleAttribute = 'display_name';

    /** Pending kitchens waiting for a decision, shown as a badge in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $pending = Kitchen::where('status', KitchenStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return KitchenForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KitchenInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KitchensTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // Cooks register themselves from the app.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKitchens::route('/'),
            'view' => ViewKitchen::route('/{record}'),
            'edit' => EditKitchen::route('/{record}/edit'),
        ];
    }
}
