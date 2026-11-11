<?php

namespace App\Filament\Resources\Orders;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Actions\ActForCookActions;
use App\Filament\Actions\RemovalActions;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Support\Links;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Reviews\BoxReviews;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/** F26: every regular customer's box, today by default. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'অর্ডার';

    protected static ?string $pluralModelLabel = 'অর্ডার (বক্স)';

    protected static ?int $navigationSort = 20;

    /** F49: the admin search box finds a box by its number ("১১-১" or "11-1"), latest first. */
    public static function canGloballySearch(): bool
    {
        return true;
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $ascii = strtr(trim($search), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        if (! preg_match('/^(\d{1,2})\s*-\s*(\d{1,5})$/', $ascii, $m)) {
            return collect();
        }

        return Order::query()
            ->whereRaw('DAY(service_date) = ?', [(int) $m[1]])->where('seq', (int) $m[2])
            ->whereDate('service_date', '>=', now()->subDays(370)->toDateString())
            ->with(['kitchen', 'customer.user', 'buyer'])->latest('service_date')->limit(5)->get()
            ->map(fn (Order $o) => new GlobalSearchResult(
                title: 'বক্স '.$o->code().' · '.$o->service_date->format('j M Y'),
                url: self::getUrl('view', ['record' => $o]),
                details: ['গ্রাহক' => $o->recipient()?->name, 'গৃহিণী' => $o->kitchen?->display_name],
            ));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer.user', 'buyer', 'kitchen', 'shahidar']))
            ->defaultSort('seq', 'desc')
            ->recordUrl(fn (Order $record) => Links::order($record))
            ->columns([
                TextColumn::make('seq')->label('বক্স')->state(fn (Order $record) => $record->code())->weight('semibold'),
                TextColumn::make('meal_slot')->label('বেলা'),
                TextColumn::make('recipient')->label('গ্রাহক')->state(fn (Order $record) => $record->recipient()->name ?: $record->recipient()->phone)
                    ->description(fn (Order $record) => $record->isOneOff() ? ($record->isSelfPickup() ? 'একবারের · নিজে নেবেন' : 'একবারের') : null),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী')->url(fn (Order $record) => Links::kitchen($record->kitchen_id)),
                TextColumn::make('shahidar.name')->label('শাহিদার')->url(fn (Order $record) => Links::shahidar($record->shahidar_id)),
                TextColumn::make('plates')->label('প্লেট'),
                TextColumn::make('total')->label('মোট')->state(fn (Order $record) => '৳'.number_format($record->totalPaisa() / 100)),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->description(fn (Order $record) => $record->cancel_reason ?? $record->failure_reason),
                TextColumn::make('confirm_method')->label('নিশ্চিত')->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'customer_scan' => 'গ্রাহক স্ক্যান',
                        'shahidar_code' => 'কোড দিয়ে',
                        'shahidar_tap' => 'যাচাই বাকি',
                        default => $state,
                    })
                    ->badge()->color(fn (?string $state) => $state === 'shahidar_tap' ? 'warning' : 'success'),
            ])
            ->filters([
                Filter::make('service_date')
                    ->schema([DatePicker::make('date')->label('তারিখ')->default(BookingPlanner::today())->native(false)])
                    ->query(fn (Builder $query, array $data) => $query->when($data['date'] ?? null, fn ($q, $d) => $q->whereDate('service_date', $d)))
                    ->indicateUsing(fn (array $data) => ($data['date'] ?? null) ? 'তারিখ: '.Carbon::parse($data['date'])->format('j M Y') : null),
                SelectFilter::make('status')->label('অবস্থা')->options(OrderStatus::class),
                SelectFilter::make('meal_slot')->label('বেলা')->options(MealSlot::class),
                SelectFilter::make('kind')->label('ধরন')->options(['regular' => 'নিয়মিত', 'oneoff' => 'একবারের (নিতে চাই)']),
                Filter::make('unverified')->label('যাচাই বাকি (কোড/স্ক্যান ছাড়া)')
                    ->query(fn (Builder $query) => $query->where('confirm_method', 'shahidar_tap')),
                SelectFilter::make('shahidar_id')->label('শাহিদার')
                    ->options(fn () => User::role(Roles::SHAHIDAR)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name ?: $u->phone])->all()),
                SelectFilter::make('kitchen_id')->label('গৃহিণী')->relationship('kitchen', 'display_name'),
            ])
            ->recordActions([
                self::acceptAction(),
                self::cancelAction(),
                ActForCookActions::decline(),
                ActForCookActions::handed(),
                RemovalActions::deleteOrder(),
            ]);
    }

    public static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('গ্রাহক নেবেন')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Asking)
            ->requiresConfirmation()
            ->modalDescription('আজকের বেশি দামে গ্রাহক রাজি হয়েছেন। বুক করে ডেলিভারি কোড SMS-এ পাঠানো হবে।')
            ->action(function (Order $record) {
                app(BookingPlanner::class)->accept($record);
                Notification::make()->title('বুক হয়েছে')->success()->send();
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('আজ বাদ')
            ->icon(Heroicon::OutlinedXMark)
            ->color('warning')
            ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::Booked, OrderStatus::Asking], true))
            ->schema([TextInput::make('reason')->label('কারণ')->required()->maxLength(200)->default('গ্রাহক ফোনে জানিয়েছেন')])
            ->action(function (Order $record, array $data) {
                app(BookingPlanner::class)->cancel($record, 'admin', $data['reason']);
                Notification::make()->title('বাদ দেওয়া হয়েছে, প্লেট ফিডে ফিরেছে')->success()->send();
            });
    }

    /** F49: one box — timeline, people, money. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('কী কী হলো')->schema([ViewEntry::make('timeline')->hiddenLabel()->view('filament.history.timeline')]),
            Section::make('কারা')->columns(2)->schema([
                TextEntry::make('recipient')->label(fn (Order $record) => $record->isOneOff() ? 'ক্রেতা (ফিড থেকে)' : 'নিয়মিত গ্রাহক')
                    ->state(fn (Order $record) => trim(($record->recipient()?->name ?? '').' · '.($record->recipient()?->phone ?? ''), ' ·'))
                    ->url(fn (Order $record) => Links::recipient($record)),
                TextEntry::make('kitchen.display_name')->label('গৃহিণী')->url(fn (Order $record) => Links::kitchen($record->kitchen_id)),
                TextEntry::make('shahidar.name')->label('শাহিদার')->placeholder('—')->url(fn (Order $record) => Links::shahidar($record->shahidar_id)),
                TextEntry::make('post.title')->label('পোস্ট')->placeholder('—')->url(fn (Order $record) => Links::post($record->post_id)),
                TextEntry::make('address')->label('ঠিকানা')->state(fn (Order $record) => $record->recipientAddress())->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('টাকা')->columns(3)->schema([
                TextEntry::make('food_paisa')->label('খাবার')->formatStateUsing(fn (int $state) => '৳'.number_format($state / 100)),
                TextEntry::make('delivery_fee_paisa')->label('ডেলিভারি')->formatStateUsing(fn (int $state) => '৳'.number_format($state / 100)),
                TextEntry::make('total')->label('মোট')->state(fn (Order $record) => '৳'.number_format($record->totalPaisa() / 100))->weight('bold'),
                TextEntry::make('payment')->label('টাকার অবস্থা')->state(fn (Order $record) => BoxReviews::paymentLabel($record))->placeholder('—'),
                TextEntry::make('bill.number')->label('বিল')->placeholder('—')->url(fn (Order $record) => Links::bill($record->bill_id)),
                TextEntry::make('cook_settlement_id')->label('গৃহিণীকে পরিশোধ')->formatStateUsing(fn ($state) => $state ? 'হয়েছে' : null)->placeholder('এখনো না'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
