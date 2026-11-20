<?php

namespace App\Filament\Resources\BotFacts;

use App\Filament\Resources\BotFacts\Pages\ManageBotFacts;
use App\Filament\Support\FeatureGate;
use App\Models\BotFact;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

/**
 * F82: "বটের জ্ঞান" — what the chat bots may tell people about Dawat. The
 * Messenger bot reads the active rows on every answer; a WhatsApp bot will read
 * the same list.
 */
class BotFactResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = BotFact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'রাঁধুনি ও খাবার';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'বটের তথ্য';

    protected static ?string $pluralModelLabel = 'বটের জ্ঞান';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('topic')->label('বিষয়')->required()->maxLength(120)
                    ->placeholder('যেমন: টাকা কখন পাবেন'),
                Select::make('audience')->label('কার জন্য')->options(BotFact::AUDIENCES)->default('all')->required(),
                Textarea::make('body')->label('বট কী বলবে')->required()->rows(6)->columnSpanFull()
                    ->helperText('বাংলায় সহজ করে লিখুন — বট নিজে বাংলিশে বলবে। শুধু যা সত্যি তাই লিখুন; বট এর বাইরে কিছু বানিয়ে বলবে না। কোনো বিষয়ে টিমকে দেখাতে চাইলে লিখুন "মানুষ লাগবে চিহ্ন দিন"।'),
                TextInput::make('feature')->label('ফিচার নম্বর')->maxLength(40)->placeholder('যেমন F61'),
                TextInput::make('sort_order')->label('ক্রম')->numeric()->default(fn () => (int) BotFact::max('sort_order') + 10),
                Toggle::make('is_active')->label('চালু')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('topic')->label('বিষয়')->weight('semibold')->searchable(),
                TextColumn::make('body')->label('বট কী বলবে')->wrap()->limit(220)->searchable(),
                TextColumn::make('audience')->label('কার জন্য')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => BotFact::AUDIENCES[$state] ?? $state),
                TextColumn::make('feature')->label('ফিচার')->toggleable(),
                IconColumn::make('is_active')->label('চালু')->boolean(),
                TextColumn::make('updated_at')->label('বদল')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('audience')->label('কার জন্য')->options(BotFact::AUDIENCES),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn () => (bool) auth()->user()?->isAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBotFacts::route('/'),
        ];
    }
}
