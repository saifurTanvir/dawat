<?php

namespace App\Filament\Resources\Customers;

use App\Actions\EnsurePerson;
use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Filament\Actions\RemovalActions;
use App\Filament\RelationManagers\CustomerOrdersRelationManager;
use App\Filament\RelationManagers\CustomerReviewsRelationManager;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Support\PhoneField;
use App\Models\Area;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Billing;
use App\Services\Orders\BookingPlanner;
use App\Services\Regular\PairingSuggester;
use App\Support\ContactHours;
use App\Support\Geo\MapLink;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            TextInput::make('max_price_taka')->label('প্রতি প্লেট সর্বোচ্চ দাম (৳)')->numeric()->minValue(20)->maxValue(2000)
                ->helperText('এর বেশি দাম হলে নিজে থেকে বুক না হয়ে গ্রাহককে জিজ্ঞেস করা হবে। খালি = সীমা নেই।')
                ->afterStateHydrated(fn ($component, ?Customer $record) => $component->state($record?->max_price_paisa ? intdiv($record->max_price_paisa, 100) : null)),
            CheckboxList::make('weekdays')->label('কোন দিন')
                ->options(array_combine(Customer::ALL_WEEKDAYS, Customer::WEEKDAY_LABELS))
                ->default(Customer::ALL_WEEKDAYS)->required()->columns(7),
            Textarea::make('note')->label('খাবার নিয়ে কিছু (ঝাল কম, ডায়াবেটিস…)')->maxLength(300)->rows(2),
            Select::make('billing')->label('টাকা কীভাবে দেবেন')->options(Customer::BILLING_LABELS)->default('each')->required()->native(false)
                ->helperText('সাপ্তাহিক বিল: প্রতি শনিবার সকালে আগের সপ্তাহের বিল। মাসিক: মাসের ১ তারিখে। বিলের গ্রাহকের কাছে শাহিদার টাকা নেবেন না।'),
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
            'max_price_paisa' => filled($data['max_price_taka'] ?? null) ? (int) $data['max_price_taka'] * 100 : null,
            'weekdays' => array_map('intval', array_values($data['weekdays'])),
            'billing' => array_key_exists($data['billing'] ?? '', Customer::BILLING_LABELS) ? $data['billing'] : 'each',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::bookingFields());
    }

    /** F55: free cooks only (one cook, one customer), nearest first; the suggested one is marked. */
    public static function kitchenOptions(Customer $customer): array
    {
        $currentId = $customer->pairing?->kitchen_id;
        $rows = app(PairingSuggester::class)->freeCooks($customer);
        $suggestedId = $rows->first()['kitchen']->id ?? null;

        return $rows->mapWithKeys(fn (array $row) => [$row['kitchen']->id => sprintf(
            '%s — %s — %s%s%s',
            $row['kitchen']->display_name,
            $row['kitchen']->area?->name ?? 'এলাকা নেই',
            PairingSuggester::distanceText($row['distance_m']),
            $row['kitchen']->id === $suggestedId ? ' ★ সাজেশন' : '',
            $row['kitchen']->id === $currentId ? ' (এখনকার)' : '',
        )])->all();
    }

    /** F55: shahidars of the customer's area, fewest regular customers first. */
    public static function shahidarOptions(Customer $customer): array
    {
        return app(PairingSuggester::class)->shahidars($customer)
            ->values()
            ->mapWithKeys(fn (User $u, int $i) => [$u->id => sprintf(
                '%s (%s) — %s জন নিয়মিত গ্রাহক%s',
                $u->name,
                $u->phone,
                Order::banglaDigits($u->load),
                $i === 0 ? ' ★ সাজেশন' : '',
            )])
            ->all();
    }

    /** What the platform suggests for this customer right now. */
    private static function suggestion(Customer $customer): array
    {
        $suggester = app(PairingSuggester::class);
        $cook = $suggester->suggestCook($customer);

        return [
            'kitchen_id' => $cook['kitchen']->id ?? null,
            'kitchen_name' => $cook['kitchen']->display_name ?? null,
            'distance_m' => $cook['distance_m'] ?? null,
            'shahidar_id' => $suggester->suggestShahidar($customer)?->id,
        ];
    }

    /** F55: set the customer's home pin from a pasted map link or "lat, lng". */
    public static function pinAction(): Action
    {
        return Action::make('pin')
            ->label('বাসার পিন')
            ->icon(Heroicon::OutlinedMapPin)
            ->color('gray')
            ->modalDescription('গ্রাহকের পাঠানো লোকেশন (গুগল ম্যাপ বা হোয়াটসঅ্যাপ লিংক) বা "23.78, 90.42" পেস্ট করুন। শুধু অ্যাডমিনরা দেখবেন; কাছের খালি গৃহিণী খুঁজতে লাগে।')
            ->schema([
                TextInput::make('where')->label('লোকেশন লিংক বা অক্ষাংশ, দ্রাঘিমাংশ')
                    ->required()
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        if (! MapLink::parse($value)) {
                            $fail('এখান থেকে লোকেশন পড়া গেল না। গুগল ম্যাপের লিংক বা "23.78, 90.42" দিন।');
                        }
                    }),
            ])
            ->action(function (Customer $record, array $data) {
                $record->location = MapLink::parse($data['where']);
                $record->save();
                Notification::make()->title('বাসার পিন রাখা হয়েছে')->success()->send();
            });
    }

    public static function pairAction(): Action
    {
        return Action::make('pair')
            ->label(fn (Customer $record) => $record->pairing ? 'জুটি বদলান' : 'জুটি বাঁধুন')
            ->icon(Heroicon::OutlinedLink)
            ->color('success')
            // F55: a new pairing starts on the platform's suggestion.
            ->fillForm(function (Customer $record) {
                $suggested = self::suggestion($record);

                return [
                    'kitchen_id' => $record->pairing?->kitchen_id ?? $suggested['kitchen_id'],
                    'shahidar_id' => $record->pairing?->shahidar_id ?? $suggested['shahidar_id'],
                ];
            })
            ->schema(fn (Customer $record) => [
                Callout::make('প্ল্যাটফর্মের সাজেশন')
                    ->description(fn () => Str::after(app(PairingSuggester::class)->suggestionLine($record), 'প্ল্যাটফর্মের সাজেশন: '))
                    ->status(fn () => self::suggestion($record)['kitchen_id'] ? 'info' : 'warning'),
                Select::make('kitchen_id')->label('গৃহিণী (রান্নাঘর)')->options(self::kitchenOptions($record))->required()->searchable()->live()
                    ->helperText('শুধু অনুমোদিত আর খালি গৃহিণী — একজন গৃহিণী একজন গ্রাহকের সাথেই জোড়া হন। কাছের জন আগে।'),
                Callout::make('আপনি সাজেশনের বাইরে বেছেছেন')
                    ->description(fn () => 'সাজেশন ছিল '.self::suggestion($record)['kitchen_name'].'। অন্যজনকে বেছে নেওয়া যায় — ঠিক থাকলে সেভ করুন, জুটির ইতিহাসে লেখা থাকবে।')
                    ->warning()
                    ->visible(fn (Get $get) => ($s = self::suggestion($record)['kitchen_id']) && $get('kitchen_id') && (int) $get('kitchen_id') !== $s),
                Select::make('shahidar_id')->label('শাহিদার')->options(self::shahidarOptions($record))->required()
                    ->helperText('এই এলাকার শাহিদার, যার হাতে কম গ্রাহক তিনি আগে। একজন শাহিদার অনেকজনকে দিতে পারেন।'),
                Textarea::make('reason')->label('বদলানোর কারণ')->maxLength(300)->rows(2)
                    ->visible(fn () => $record->pairing !== null)->required(fn () => $record->pairing !== null),
            ])
            ->action(function (Customer $record, array $data) {
                $suggested = self::suggestion($record);
                $pairing = app(PairCustomer::class)(
                    $record,
                    Kitchen::findOrFail($data['kitchen_id']),
                    User::findOrFail($data['shahidar_id']),
                    auth()->user(),
                    $data['reason'] ?? null,
                    $suggested,
                );
                Notification::make()->title('জুটি বাঁধা হয়েছে')
                    ->body($pairing->followedSuggestion() === false ? "সাজেশন ছিল {$suggested['kitchen_name']}, আপনি বেছেছেন {$pairing->kitchen->display_name}।" : null)
                    ->success()->send();
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

    public static function pauseAction(): Action
    {
        return Action::make('pause')
            ->label('বিরতি দিন')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->visible(fn (Customer $record) => $record->isActive())
            ->schema([
                DatePicker::make('starts_on')->label('কবে থেকে')->required()->native(false)->default(BookingPlanner::today()),
                DatePicker::make('ends_on')->label('কবে পর্যন্ত')->required()->native(false)->afterOrEqual('starts_on'),
                TextInput::make('reason')->label('কারণ')->maxLength(200),
            ])
            ->action(function (Customer $record, array $data) {
                app(BookingPlanner::class)->pause(
                    $record,
                    Carbon::parse($data['starts_on'])->toDateString(),
                    Carbon::parse($data['ends_on'])->toDateString(),
                    auth()->id(),
                    $data['reason'] ?? null,
                );
                Notification::make()->title('বিরতি দেওয়া হয়েছে')->success()->send();
            });
    }

    /** F45: the cook posted before the pairing (or booking change): book from that post now. */
    public static function bookNowAction(): Action
    {
        return Action::make('bookNow')
            ->label('আজকের পোস্ট থেকে বুক করুন')
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->visible(fn (Customer $record) => collect(app(BookingPlanner::class)->todayReasons($record))->contains('can_book', true))
            ->requiresConfirmation()
            ->modalDescription('গৃহিণীর আজকের চালু পোস্ট থেকে এই গ্রাহকের বক্স বুক হবে। গ্রাহক আর শাহিদার নোটিফিকেশন পাবেন।')
            ->action(function (Customer $record) {
                $n = app(BookingPlanner::class)->bookLate($record)->count();
                Notification::make()->title($n ? Order::banglaDigits((string) $n).'টা বক্স বুক হয়েছে' : 'বুক করার মতো প্লেট পাওয়া যায়নি')
                    ->{$n ? 'success' : 'warning'}()->send();
            });
    }

    /** F39: bill the boxes received so far (or up to a date). */
    public static function issueBillAction(): Action
    {
        return Action::make('issueBill')
            ->label('বিল তৈরি করুন')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->visible(fn (Customer $record) => app(Billing::class)->unbilled($record)->exists())
            ->modalDescription(fn (Customer $record) => 'বিল হয়নি এমন '.Order::banglaDigits((string) app(Billing::class)->unbilled($record)->count())
                .'টা বক্স, মোট ৳'.Order::banglaDigits((string) intdiv(app(Billing::class)->unbilledTotal($record), 100)).'। গ্রাহক অ্যাপে নোটিফিকেশন পাবেন।')
            ->schema([
                DatePicker::make('to')->label('কোন তারিখ পর্যন্ত')->native(false)->default(BookingPlanner::today())->required(),
            ])
            ->action(function (Customer $record, array $data, $livewire) {
                $bill = app(Billing::class)->issue($record, auth()->user(), Carbon::parse($data['to'])->toDateString());
                Notification::make()->title('বিল '.$bill->number.' তৈরি হয়েছে')->success()->send();
                $livewire->redirect(BillResource::getUrl('view', ['record' => $bill]));
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('সারাংশ')->columnSpanFull()->schema([ViewEntry::make('history_summary')->hiddenLabel()->view('filament.history.summary-customer')]),
            Section::make('গ্রাহক')->columns(3)->schema([
                TextEntry::make('user.name')->label('নাম'),
                TextEntry::make('user.phone')->label('ফোন')->copyable(),
                TextEntry::make('status')->label('অবস্থা')->badge(),
                TextEntry::make('call_now')->label('কল করা যাবে?')->columnSpan(2)
                    ->state(fn (Customer $record) => ContactHours::adminLine($record->user, $record->todayOrders()->pluck('meal_slot')->all()))
                    ->url(fn (Customer $record) => ContactHours::whatsappUrl($record->user->phone), shouldOpenInNewTab: true)
                    ->helperText('ক্লিক করলে হোয়াটসঅ্যাপ খুলবে — ফ্রি মেসেজ বা কল।'),
                TextEntry::make('area.name')->label('এলাকা'),
                TextEntry::make('address')->label('ঠিকানা')->columnSpan(2),
                // F55: admins only; used to find the nearest free cook.
                TextEntry::make('home_pin')->label('বাসার পিন')
                    ->state(fn (Customer $record) => $record->location ? 'দেওয়া আছে — ম্যাপে দেখুন' : 'নেই (দূরত্ব এলাকার মাঝখান থেকে আন্দাজ)')
                    ->url(fn (Customer $record) => $record->location ? MapLink::googleMaps($record->location) : null, shouldOpenInNewTab: true)
                    ->color(fn (Customer $record) => $record->location ? 'primary' : 'gray'),
                TextEntry::make('booking')->label('বুকিং')->state(fn (Customer $record) => $record->bookingSummary())->columnSpan(2),
                TextEntry::make('note')->label('খাবার নিয়ে')->placeholder('—'),
                TextEntry::make('billing')->label('টাকা দেন')->formatStateUsing(fn (?string $state) => Customer::BILLING_LABELS[$state] ?? '—'),
                TextEntry::make('unbilled')->label('বিল হয়নি এমন')
                    ->state(fn (Customer $record) => '৳'.Order::banglaDigits((string) intdiv(app(Billing::class)->unbilledTotal($record), 100))),
                TextEntry::make('billed_due')->label('বিলের বাকি')
                    ->state(fn (Customer $record) => '৳'.Order::banglaDigits((string) intdiv((int) $record->bills()->whereIn('status', ['open', 'partial'])->get()->sum(fn (Bill $b) => $b->duePaisa()), 100))),
            ]),
            Section::make('আজ')
                ->description('বুকিংয়ের প্রতিটা বেলায় আজ কী হলো, বক্স না থাকলে কেন।')
                ->schema([
                    TextEntry::make('today_reasons')->hiddenLabel()->listWithLineBreaks()
                        ->state(fn (Customer $record) => collect(app(BookingPlanner::class)->todayReasons($record))
                            ->map(fn ($r) => ($r['ok'] ? '✅ ' : '⚠️ ').$r['label'].': '.$r['line'])->all() ?: ['বুকিংয়ে কোনো বেলা নেই']),
                ]),
            Section::make('বিল')->schema([
                RepeatableEntry::make('bills')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('number')->label('বিল নং')
                        ->url(fn (Bill $record) => BillResource::getUrl('view', ['record' => $record])),
                    TextEntry::make('period')->label('সময়')->state(fn (Bill $record) => $record->period_from->format('j M').' – '.$record->period_to->format('j M')),
                    TextEntry::make('total_paisa')->label('মোট')->formatStateUsing(fn (int $state) => '৳'.number_format(intdiv($state, 100))),
                    TextEntry::make('due')->label('বাকি')->state(fn (Bill $record) => '৳'.number_format(intdiv($record->duePaisa(), 100))),
                    TextEntry::make('status')->label('অবস্থা')->badge()->formatStateUsing(fn (string $state) => Bill::STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state) => BillResource::statusColor($state)),
                ])->placeholder('এখনো কোনো বিল নেই'),
            ])->collapsible(),
            Section::make('বিরতি')->schema([
                RepeatableEntry::make('upcomingPauses')->hiddenLabel()->columns(3)->schema([
                    TextEntry::make('starts_on')->label('থেকে')->date('j M Y'),
                    TextEntry::make('ends_on')->label('পর্যন্ত')->date('j M Y'),
                    TextEntry::make('reason')->label('কারণ')->placeholder('—'),
                ])->placeholder('কোনো বিরতি নেই'),
            ])->collapsible(),
            Section::make('জুটির ইতিহাস')->schema([
                RepeatableEntry::make('pairings')->hiddenLabel()->columns(4)->schema([
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
                TextColumn::make('billing')->label('টাকা')->badge()->color('gray')->formatStateUsing(fn (?string $state) => Customer::BILLING_LABELS[$state] ?? '—'),
                TextColumn::make('created_at')->label('যোগ')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(CustomerStatus::class),
                SelectFilter::make('area_id')->label('এলাকা')->relationship('area', 'name'),
                SelectFilter::make('billing')->label('টাকা দেন')->options(Customer::BILLING_LABELS),
            ])
            ->recordActions([
                ViewAction::make(),
                self::pairAction(),
                ActionGroup::make([
                    EditAction::make()->mutateDataUsing(fn (array $data) => self::bookingData($data))
                        ->after(fn (Customer $record) => app(BookingPlanner::class)->bookLate($record)),
                    RemovalActions::unpair(),
                    RemovalActions::restart(),
                    RemovalActions::deleteCustomer(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [CustomerOrdersRelationManager::class, CustomerReviewsRelationManager::class];
    }

    /** F49: find a regular customer by name or phone. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'user.phone', 'address'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return ($record->user?->name ?: 'গ্রাহক').' · '.$record->user?->phone;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
