<?php

namespace App\Filament\Resources\Shahidars;

use App\Actions\EnsurePerson;
use App\Filament\Actions\RemovalActions;
use App\Filament\RelationManagers\KitchenActionsRelationManager;
use App\Filament\RelationManagers\ShahidarOrdersRelationManager;
use App\Filament\RelationManagers\ShahidarPairingsRelationManager;
use App\Filament\RelationManagers\ShahidarReviewsRelationManager;
use App\Filament\RelationManagers\ShahidarSettlementsRelationManager;
use App\Filament\Resources\Shahidars\Pages\ManageShahidars;
use App\Filament\Resources\Shahidars\Pages\ViewShahidar;
use App\Filament\Support\PhoneField;
use App\Models\Area;
use App\Models\User;
use App\Support\ContactHours;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** F24: trusted local runners and the areas (zones) they serve. */
class ShahidarResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'shahidars';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'শাহিদার';

    protected static ?string $pluralModelLabel = 'শাহিদার';

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(Roles::SHAHIDAR);
    }

    public static function areasField(): Select
    {
        return Select::make('shahidarAreas')
            ->label('কোন এলাকায় কাজ করবেন')
            ->relationship('shahidarAreas', 'name')
            ->multiple()
            ->preload()
            ->required();
    }

    public static function createForm(): array
    {
        return [
            PhoneField::make()
                ->helperText('এই নম্বর দিয়ে OTP-তে লগইন করবেন। আগে থেকে অ্যাকাউন্ট থাকলে সেটাতেই শাহিদার রোল যোগ হবে।'),
            TextInput::make('name')->label('নাম')->required()->maxLength(60),
            Select::make('area_ids')->label('কোন এলাকায় কাজ করবেন')
                ->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))
                ->multiple()->required(),
        ];
    }

    /** Creates or promotes the user, then sets their areas. */
    public static function createShahidar(array $data): User
    {
        $user = app(EnsurePerson::class)($data['phone'], $data['name'], Roles::SHAHIDAR);
        $user->shahidarAreas()->sync($data['area_ids']);

        return $user;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(60),
            self::areasField(),
        ]);
    }

    /** F49: a shahidar's page. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('সারাংশ')->columnSpanFull()->schema([ViewEntry::make('history_summary')->hiddenLabel()->view('filament.history.summary-shahidar')]),
            Section::make('শাহিদার')->columns(3)->schema([
                TextEntry::make('name')->label('নাম'),
                TextEntry::make('phone')->label('ফোন')->copyable(),
                TextEntry::make('status')->label('অবস্থা')->badge(),
                TextEntry::make('shahidarAreas.name')->label('এলাকা')->badge()->color('success'),
                TextEntry::make('call_now')->label('কল করা যাবে?')->state(fn (User $record) => ContactHours::adminLine($record))->columnSpan(2),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ShahidarOrdersRelationManager::class, ShahidarSettlementsRelationManager::class, ShahidarPairingsRelationManager::class, KitchenActionsRelationManager::class, ShahidarReviewsRelationManager::class];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return 'শাহিদার: '.($record->name ?: $record->phone);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordUrl(fn (User $record) => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable()->weight('semibold'),
                TextColumn::make('phone')->label('ফোন')->searchable()->copyable(),
                TextColumn::make('shahidarAreas.name')->label('এলাকা')->badge()->color('success'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                RemovalActions::removeShahidar(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShahidars::route('/'),
            'view' => ViewShahidar::route('/{record}'),
        ];
    }
}
