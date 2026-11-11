<?php

namespace App\Filament\Resources\Users;

use App\Filament\RelationManagers\BuyerOrdersRelationManager;
use App\Filament\RelationManagers\SmsLogsRelationManager;
use App\Filament\RelationManagers\UserReviewsRelationManager;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'নিরাপত্তা';

    protected static ?string $modelLabel = 'ইউজার';

    protected static ?string $pluralModelLabel = 'ইউজার';

    protected static ?string $recordTitleAttribute = 'phone';

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // People sign up themselves with their phone.
        return false;
    }

    public static function getRelations(): array
    {
        return [BuyerOrdersRelationManager::class, UserReviewsRelationManager::class, SmsLogsRelationManager::class];
    }

    /** F49: find anyone by phone or name. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['phone', 'name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return ($record->name ?: 'নাম নেই').' · '.$record->phone;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
