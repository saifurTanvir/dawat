<?php

namespace App\Filament\Resources\Customers;

use App\Actions\EnsurePerson;
use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Support\PhoneField;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** F25: regular customers, their standing booking and who feeds and delivers to them. */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'নিয়মিত গ্রাহক';

    protected static ?string $pluralModelLabel = 'নিয়মিত গ্রাহক';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $waiting = Customer::where('status', CustomerStatus::Waiting)->count();

        return $waiting ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Fields shared by create and edit. */
    public static function bookingFields(): array
    {
        return [
            Select::make('area_id')->label('এলাকা')
                ->options(fn () => Area::active()->orderBy('sort_order')->pluck('name', 'id'))
                ->required(),
            Textarea::make('address')->label('ডেলিভারির ঠিকানা')->required()->maxLength(255)->rows(2)
                ->helperText('বাসা, রোড, ফ্ল্যাট, চেনার মতো কিছু। শুধু অ্যাডমিন আর এই গ্রাহকের শাহিদার দেখবেন।'),
            CheckboxList::make('meal_slots')->label('কোন বেলা')->options(MealSlot::options())->required()->columns(4),
            TextInput::make('plates')->label('প্রতি বেলায় কয় প্লেট')->numeric()->minValue(1)->maxValue(10)->default(1)->required(),
            CheckboxList::make('weekdays')->label('কোন দিন')
                ->options(array_combine(Customer::ALL_WEEKDAYS, Customer::WEEKDAY_LABELS))
                ->default(Customer::ALL_WEEKDAYS)->required()->columns(7),
            Textarea::make('note')->label('খাবার নিয়ে কিছু (ঝাল কম, ডায়াবেটিস…)')->maxLength(300)->rows(2),
        ];
    }

    public static function createFields(): array
    {
        return [
            PhoneField::make()->helperText('এই নম্বরে OTP দিয়ে গ্রাহক নিজেও অ্যাপে দেখতে পারবেন।'),
            TextInput::make('name')->label('নাম')->required()->maxLength(60),
            ...self::bookingFields(),
        ];
    }

    public static function createCustomer(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $user = app(EnsurePerson::class)($data['phone'], $data['name'], Roles::BUYER);
            if ($user->customer) {
                // Asking again after stopping, or re-entered by an admin: update in place.
                $user->customer->fill(self::bookingData($data))->save();

                return $user->customer;
            }

            return Customer::create([...self::bookingData($data), 'user_id' => $user->id, 'source' => 'admin']);
        });
    }

    public static function bookingData(array $data): array
    {
        return [
            'area_id' => $data['area_id'],
            'address' => trim($data['address']),
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'meal_slots' => array_values($data['meal_slots']),
            'plates' => (int) $data['plates'],
            'weekdays' => array_map('intval', array_values($data['weekdays'])),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::bookingFields());
    }

    /** Cooks offered for pairing: approved, same area first, most free slots first. */
    public static function kitchenOptions(Customer $customer): array
    {
        $currentId = $customer->pairing?->kitchen_id;

        return Kitchen::query()
            ->where('status', KitchenStatus::Approved)
            ->with('area')
            ->withCount('pairings')
            ->get()
            ->sortBy(fn (Kitchen $k) => [$k->area_id === $customer->area_id ? 0 : 1, -$k->freeRegularSlots()])
            ->mapWithKeys(fn (Kitchen $k) => [$k->id => sprintf(
                '%s — %s — খালি %d/%d%s',
                $k->display_name,
                $k->area?->name ?? 'এলাকা নেই',
                $k->freeRegularSlots(),
                $k->max_regular_customers,
                $k->id === $currentId ? ' (এখনকার)' : '',
            )])
            ->all();
    }

    public static function shahidarOptions(Customer $customer): array
    {
        return User::role(Roles::SHAHIDAR)
            ->whereHas('shahidarAreas', fn ($q) => $q->whereKey($customer->area_id))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->phone})"])
            ->all();
    }

    public static function pairAction(): Action
    {
        return Action::make('pair')
            ->label(fn (Customer $record) => $record->pairing ? 'জুটি বদলান' : 'জুটি বাঁধুন')
            ->icon(Heroicon::OutlinedLink)
            ->color('success')
            ->fillForm(fn (Customer $record) => [
                'kitchen_id' => $record->pairing?->kitchen_id,
                'shahidar_id' => $record->pairing?->shahidar_id,
            ])
            ->schema(fn (Customer $record) => [
                Select::make('kitchen_id')->label('গৃহিণী (রান্নাঘর)')->options(self::kitchenOptions($record))->required()->searchable()
                    ->helperText('একই এলাকার আর বেশি জায়গা খালি এমন রান্নাঘর আগে।'),
                Select::make('shahidar_id')->label('শাহিদার')->options(self::shahidarOptions($record))->required()
                    ->helperText('এই এলাকার শাহিদার। কেউ না থাকলে আগে "শাহিদার" পাতায় যোগ করুন।'),
                Textarea::make('reason')->label('বদলানোর কারণ')->maxLength(300)->rows(2)
                    ->visible(fn () => $record->pairing !== null)->required(fn () => $record->pairing !== null),
            ])
            ->action(function (Customer $record, array $data) {
                app(PairCustomer::class)(
                    $record,
                    Kitchen::findOrFail($data['kitchen_id']),
                    User::findOrFail($data['shahidar_id']),
                    auth()->user(),
                    $data['reason'] ?? null,
                );
                Notification::make()->title('জুটি বাঁধা হয়েছে')->success()->send();
            });
    }

    public static function stopAction(): Action
    {
        return Action::make('stop')
            ->label('নিয়মিত খাবার বন্ধ')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Customer $record) => $record->status !== CustomerStatus::Stopped)
            ->schema([Textarea::make('reason')->label('কারণ')->required()->maxLength(300)])
            ->action(function (Customer $record, array $data) {
                PairCustomer::stop($record, auth()->user(), $data['reason']);
                Notification::make()->title('বন্ধ করা হয়েছে')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('গ্রাহক')->columns(3)->schema([
                TextEntry::make('user.name')->label('নাম'),
                TextEntry::make('user.phone')->label('ফোন')->copyable(),
                TextEntry::make('status')->label('অবস্থা')->badge(),
                TextEntry::make('area.name')->label('এলাকা'),
                TextEntry::make('address')->label('ঠিকানা')->columnSpan(2),
                TextEntry::make('booking')->label('বুকিং')->state(fn (Customer $record) => $record->bookingSummary())->columnSpan(2),
                TextEntry::make('note')->label('খাবার নিয়ে')->placeholder('—'),
            ]),
            Section::make('জুটির ইতিহাস')->schema([
                RepeatableEntry::make('pairings')->label('')->columns(4)->schema([
                    TextEntry::make('kitchen.display_name')->label('রান্নাঘর'),
                    TextEntry::make('shahidar.name')->label('শাহিদার'),
                    TextEntry::make('started_at')->label('শুরু')->dateTime('j M Y'),
                    TextEntry::make('ended_at')->label('শেষ')->dateTime('j M Y')->placeholder('চলছে'),
                    TextEntry::make('end_reason')->label('বদলের কারণ')->placeholder('—')->columnSpanFull(),
                ])->placeholder('এখনো জুটি বাঁধা হয়নি'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'area', 'pairing.kitchen', 'pairing.shahidar']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('নাম')->searchable()->weight('semibold'),
                TextColumn::make('user.phone')->label('ফোন')->searchable(),
                TextColumn::make('area.name')->label('এলাকা'),
                TextColumn::make('status')->label('অবস্থা')->badge(),
                TextColumn::make('pairing.kitchen.display_name')->label('গৃহিণী')->placeholder('—'),
                TextColumn::make('pairing.shahidar.name')->label('শাহিদার')->placeholder('—'),
                TextColumn::make('booking')->label('বুকিং')->state(fn (Customer $record) => $record->bookingSummary())->wrap(),
                TextColumn::make('created_at')->label('যোগ')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(CustomerStatus::class),
                SelectFilter::make('area_id')->label('এলাকা')->relationship('area', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                self::pairAction(),
                EditAction::make()->mutateDataUsing(fn (array $data) => self::bookingData($data)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
