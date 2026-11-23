<?php

namespace App\Filament\Resources\Companies;

use App\Actions\EnsurePerson;
use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Support\FeatureGate;
use App\Filament\Support\LocationField;
use App\Filament\Support\PhoneField;
use App\Models\Area;
use App\Models\Company;
use App\Support\Geo\Geocoder;
use App\Support\PhoneNumber;
use App\Support\Roles;
use BackedEnum;
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
use Filament\Tables\Table;
use UnitEnum;

/**
 * F68: offices whose employees eat Dawat's lunch. Employees are regular
 * customers with this office chosen; their boxes go as one office drop,
 * handed to the contact person with one code.
 */
class CompanyResource extends Resource
{
    use FeatureGate;

    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'অফিস';

    protected static ?string $pluralModelLabel = 'অফিস';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('অফিসের নাম')->required()->maxLength(150),
            Select::make('area_id')->label('এলাকা')->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))->required()->searchable(),
            Textarea::make('address')->label('ঠিকানা')->required()->maxLength(300)->rows(2)
                ->helperText('ভবন, তলা, রিসেপশন — এলাকা ম্যানেজার যেখানে খাবার বুঝিয়ে দেবেন।'),
            TextInput::make('contact_name')->label('দায়িত্বপ্রাপ্ত লোকের নাম')->required()->maxLength(60)
                ->afterStateHydrated(fn ($component, ?Company $record) => $component->state($record?->contact?->name)),
            PhoneField::make('contact_phone')->label('দায়িত্বপ্রাপ্ত লোকের মোবাইল')
                ->helperText('প্রতিদিন খাবার বুক হলে এই নম্বরে বুঝিয়ে নেওয়ার কোড যাবে।')
                ->afterStateHydrated(fn ($component, ?Company $record) => $component->state($record?->contact?->phone)),
            // F95: the round's last stop on the map
            LocationField::make('where', 'অফিসের লোকেশন (পিন)')
                ->helperText('এলাকা ম্যানেজারের রাউন্ডে শেষ গন্তব্য — ম্যাপ এখানেই পথ দেখাবে। শুধু অ্যাডমিন আর সেই এলাকা ম্যানেজার দেখেন।')
                ->afterStateHydrated(fn ($component, ?Company $record) => $component->state($record?->location ? sprintf('%.6f, %.6f', $record->location->lat, $record->location->lng) : null)),
            TextInput::make('drop_note')->label('ডেলিভারির নোট')->maxLength(200)->placeholder('যেমন: দুপুর ১২:৩০-এর মধ্যে, রিসেপশনে'),
            Toggle::make('is_active')->label('চালু')->default(true),
        ]);
    }

    /** The form's contact name and phone become the contact's account. */
    public static function withContact(array $data): array
    {
        $phone = PhoneNumber::normalize((string) ($data['contact_phone'] ?? ''));
        $data['contact_user_id'] = $phone ? app(EnsurePerson::class)($phone, $data['contact_name'] ?? null, Roles::BUYER)->id : null;
        unset($data['contact_phone'], $data['contact_name']);
        if (array_key_exists('where', $data)) {
            $data['location'] = app(Geocoder::class)->point($data['where']);
            unset($data['where']);
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['area', 'contact'])->withCount(['customers' => fn ($q) => $q->where('status', 'active')]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('অফিস')->searchable()->weight('semibold')->description(fn (Company $c) => $c->address),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('contact.name')->label('দায়িত্বপ্রাপ্ত')->description(fn (Company $c) => $c->contact?->phone)->placeholder('—'),
                TextColumn::make('customers_count')->label('কর্মী (চালু)'),
                TextColumn::make('drop_note')->label('নোট')->placeholder('—')->wrap(),
                IconColumn::make('pin')->label('পিন')->state(fn (Company $c) => $c->location !== null)->boolean(),
                IconColumn::make('is_active')->label('চালু')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data) => self::withContact($data)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCompanies::route('/')];
    }
}
