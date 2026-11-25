<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\FeeFields;
use App\Models\Area;
use App\Support\Geo\GeoPoint;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use UnitEnum;

class AreaResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = Area::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    /** An area is also a delivery zone: its shahidars serve the kitchens and customers in it. */
    protected static ?string $modelLabel = 'এলাকা';

    protected static ?string $pluralModelLabel = 'এলাকা';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('নাম (বাংলায়)')->required()->maxLength(60),
                TextInput::make('slug')
                    ->label('লিংকের নাম (ইংরেজি, যেমন mirpur-10)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rule('alpha_dash')
                    ->dehydrateStateUsing(fn (string $state) => Str::slug($state)),
                Grid::make(2)->schema([
                    TextInput::make('center_lat')
                        ->label('কেন্দ্রের অক্ষাংশ (lat)')
                        ->helperText('Google Maps-এ জায়গায় চেপে ধরলে প্রথম সংখ্যাটি')
                        ->numeric()->required()->minValue(20)->maxValue(27),
                    TextInput::make('center_lng')
                        ->label('কেন্দ্রের দ্রাঘিমাংশ (lng)')
                        ->helperText('দ্বিতীয় সংখ্যাটি')
                        ->numeric()->required()->minValue(88)->maxValue(93),
                ]),
                TextInput::make('radius_m')->label('ব্যাসার্ধ (মিটার)')->numeric()->required()->default(2000)->minValue(300)->maxValue(10000),
                TextInput::make('delivery_fee_taka')->label('নিয়মিত গ্রাহকের ডেলিভারি চার্জ (৳, প্রতি বক্স)')->numeric()->minValue(0)->maxValue(500)->default(0)
                    ->afterStateHydrated(fn ($component, ?Area $record) => $component->state(intdiv($record?->delivery_fee_paisa ?? 0, 100))),
                TextInput::make('sort_order')->label('ক্রম')->numeric()->default(0),
                Toggle::make('is_active')->label('চালু')->default(true),
                // F111
                Toggle::make('for_customers')->label('গ্রাহকের তালিকায় দেখাবে')->helperText('নিয়মিত গ্রাহক যোগ করার সময় এলাকার তালিকায় শুধু টিক দেওয়া এলাকাগুলো আসে — যেখানে এখন ডেলিভারি চলে।'),
                // F56
                FeeFields::section('এই এলাকার গৃহিণীদের জন্য। খালি রাখলে "সবার জন্য" নিয়ম চলবে; কোনো গৃহিণীর নিজের নিয়ম থাকলে সেটাই আগে।')->columnSpanFull()->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable()->weight('semibold'),
                TextColumn::make('slug')->label('লিংক'),
                TextColumn::make('radius_m')->label('ব্যাসার্ধ')->suffix(' মি'),
                TextColumn::make('kitchens_count')->label('রান্নাঘর')->counts('kitchens'),
                TextColumn::make('shahidars.name')->label('এলাকা ম্যানেজার')->badge()->color('success')->placeholder('—'),
                IconColumn::make('is_active')->label('চালু')->boolean(),
                // F111: tap to add or remove an area from the customer form's list
                ToggleColumn::make('for_customers')->label('গ্রাহকের তালিকায়'),
            ])
            ->filters([
                TernaryFilter::make('for_customers')->label('গ্রাহকের তালিকায়'),
            ])
            ->recordActions([
                EditAction::make()
                    // The POINT itself can't go into a Livewire form; edit it as two numbers.
                    ->mutateRecordDataUsing(fn (array $data, Area $record) => [
                        ...Arr::except($data, ['center']),
                        'center_lat' => $record->center?->lat,
                        'center_lng' => $record->center?->lng,
                        'delivery_fee_taka' => intdiv((int) $record->delivery_fee_paisa, 100),
                    ])
                    ->mutateDataUsing(fn (array $data) => self::withCenter($data)),
            ]);
    }

    /** The form edits lat/lng as two numbers; the model stores one POINT. */
    public static function withCenter(array $data): array
    {
        $data['center'] = GeoPoint::make($data['center_lat'], $data['center_lng']);
        if (array_key_exists('delivery_fee_taka', $data)) {
            $data['delivery_fee_paisa'] = (int) $data['delivery_fee_taka'] * 100;
            unset($data['delivery_fee_taka']);
        }
        unset($data['center_lat'], $data['center_lng']);

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAreas::route('/'),
        ];
    }
}
