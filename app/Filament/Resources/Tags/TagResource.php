<?php

namespace App\Filament\Resources\Tags;

use App\Enums\TagType;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $modelLabel = 'ট্যাগ';

    protected static ?string $pluralModelLabel = 'ট্যাগ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('নাম (বাংলায়)')->required()->maxLength(40),
                TextInput::make('slug')
                    ->label('কোড (ইংরেজি)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => Str::slug($state)),
                Select::make('type')->label('ধরন')->options(TagType::class)->required(),
                TextInput::make('sort_order')->label('ক্রম')->numeric()->default(0),
                Toggle::make('is_active')->label('চালু')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('নাম')->weight('semibold'),
                TextColumn::make('type')->label('ধরন')->badge()->color('gray'),
                TextColumn::make('posts_count')->label('ব্যবহার')->counts('posts'),
                IconColumn::make('is_active')->label('চালু')->boolean(),
            ])
            ->recordActions([
                // Tags are switched off rather than deleted, so old posts keep them.
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
