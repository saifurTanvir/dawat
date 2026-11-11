<?php

namespace App\Filament\Resources\Bills;

use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Bill;
use App\Models\Order;
use App\Services\Orders\Billing;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
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
use UnitEnum;

/** F39: customers' weekly and monthly bills, part payments and what is still due. */
class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'নিয়মিত খাবার';

    protected static ?string $modelLabel = 'বিল';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $pluralModelLabel = 'বিল';

    protected static ?int $navigationSort = 45;

    public static function getNavigationBadge(): ?string
    {
        $open = Bill::whereIn('status', ['open', 'partial'])->count();

        return $open ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'open' => 'warning',
            'partial' => 'info',
            'paid' => 'success',
            default => 'gray',
        };
    }

    public static function taka(int $paisa): string
    {
        return '৳'.number_format(intdiv($paisa, 100));
    }

    /** The message sent to the customer on WhatsApp, with the bill link. */
    public static function whatsappUrl(Bill $bill): ?string
    {
        $phone = $bill->customer?->user?->phone;
        if (! $phone) {
            return null;
        }
        $text = "আসসালামু আলাইকুম। দাওয়াতের বিল {$bill->number}\n"
            .$bill->period_from->format('j M').' – '.$bill->period_to->format('j M').', '
            .Order::banglaDigits((string) $bill->orders_count).'টা বক্স।'
            ."\nমোট ৳".Order::banglaDigits((string) intdiv($bill->total_paisa, 100))
            .($bill->paid_paisa > 0 ? ', বাকি ৳'.Order::banglaDigits((string) intdiv($bill->duePaisa(), 100)) : '')
            ."\nবিস্তারিত: ".$bill->publicUrl();

        return 'https://wa.me/'.PhoneNumber::international($phone).'?text='.rawurlencode($text);
    }

    public static function payAction(): Action
    {
        return Action::make('pay')
            ->label('টাকা পেয়েছি')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Bill $record) => in_array($record->status, ['open', 'partial'], true))
            ->fillForm(fn (Bill $record) => ['amount_taka' => intdiv($record->duePaisa(), 100), 'method' => 'cash'])
            ->schema(fn (Bill $record) => [
                TextInput::make('amount_taka')->label('কত টাকা (৳)')->numeric()->required()->minValue(1)
                    ->maxValue((int) ceil($record->duePaisa() / 100))
                    ->helperText('বাকি '.self::taka($record->duePaisa()).'। কম দিলে বাকিটা বিলে থেকে যাবে।'),
                Select::make('method')->label('কীভাবে')->options(Bill::METHOD_LABELS)->required()->native(false),
                TextInput::make('note')->label('নোট (বিকাশ নম্বর/ট্রানজ্যাকশন)')->maxLength(200),
            ])
            ->action(function (Bill $record, array $data) {
                $amount = min((int) round(((float) $data['amount_taka']) * 100), $record->duePaisa());
                $bill = app(Billing::class)->pay($record, $amount, $data['method'], auth()->user(), $data['note'] ?? null);
                Notification::make()
                    ->title($bill->status === 'paid' ? 'বিল পুরো পরিশোধ হয়েছে' : 'জমা হয়েছে, বাকি '.self::taka($bill->duePaisa()))
                    ->success()->send();
            });
    }

    public static function voidAction(): Action
    {
        return Action::make('void')
            ->label('বিল বাতিল')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Bill $record) => $record->status === 'open' && $record->paid_paisa === 0)
            ->requiresConfirmation()
            ->modalDescription('ভুল হলে বাতিল করুন। বক্সগুলো আবার "বিল হয়নি" হয়ে যাবে, পরে নতুন বিল করা যাবে।')
            ->action(function (Bill $record) {
                app(Billing::class)->void($record);
                Notification::make()->title('বিল বাতিল হয়েছে')->success()->send();
            });
    }

    public static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label('হোয়াটসঅ্যাপে পাঠান')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->visible(fn (Bill $record) => $record->status !== 'void' && self::whatsappUrl($record) !== null)
            ->url(fn (Bill $record) => self::whatsappUrl($record), shouldOpenInNewTab: true);
    }

    public static function openAction(): Action
    {
        return Action::make('open')
            ->label('গ্রাহকের পাতা')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (Bill $record) => $record->status !== 'void')
            ->url(fn (Bill $record) => $record->publicUrl(), shouldOpenInNewTab: true);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('বিল')->columns(4)->schema([
                TextEntry::make('number')->label('বিল নং')->copyable(),
                TextEntry::make('customer.user.name')->label('গ্রাহক')
                    ->url(fn (Bill $record) => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                TextEntry::make('customer.user.phone')->label('ফোন')->copyable(),
                TextEntry::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn (string $state) => Bill::STATUS_LABELS[$state] ?? $state)->color(fn (string $state) => self::statusColor($state)),
                TextEntry::make('period')->label('সময়')->state(fn (Bill $record) => $record->period_from->format('j M Y').' – '.$record->period_to->format('j M Y')),
                TextEntry::make('orders_count')->label('বক্স'),
                TextEntry::make('food_paisa')->label('খাবার')->formatStateUsing(fn (int $state) => self::taka($state)),
                TextEntry::make('delivery_paisa')->label('ডেলিভারি')->formatStateUsing(fn (int $state) => self::taka($state)),
                TextEntry::make('total_paisa')->label('মোট')->formatStateUsing(fn (int $state) => self::taka($state))->weight('bold'),
                TextEntry::make('paid_paisa')->label('পেয়েছি')->formatStateUsing(fn (int $state) => self::taka($state)),
                TextEntry::make('due')->label('বাকি')->state(fn (Bill $record) => self::taka($record->duePaisa()))->weight('bold')->color('warning'),
                TextEntry::make('due_on')->label('দেওয়ার শেষ দিন')->date('j M Y')->placeholder('—'),
            ]),
            Section::make('টাকা জমা')->schema([
                RepeatableEntry::make('payments')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('created_at')->label('কবে')->dateTime('j M, g:i a', config('app.display_timezone')),
                    TextEntry::make('amount_paisa')->label('টাকা')->formatStateUsing(fn (int $state) => self::taka($state)),
                    TextEntry::make('method')->label('কীভাবে')->formatStateUsing(fn (string $state) => Bill::METHOD_LABELS[$state] ?? $state),
                    TextEntry::make('note')->label('নোট')->placeholder('—'),
                ])->placeholder('এখনো টাকা জমা হয়নি'),
            ]),
            Section::make('বক্সগুলো')->collapsible()->schema([
                RepeatableEntry::make('orders')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('service_date')->label('তারিখ')->date('j M, D'),
                    TextEntry::make('meal_slot')->label('বেলা')->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('kitchen.display_name')->label('রান্নাঘর'),
                    TextEntry::make('status')->label('অবস্থা')->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('total')->label('টাকা')->state(fn (Order $record) => self::taka($record->totalPaisa())),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('customer.user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('বিল নং')->searchable()->weight('semibold'),
                TextColumn::make('customer.user.name')->label('গ্রাহক')->searchable(),
                TextColumn::make('customer.user.phone')->label('ফোন')->searchable(),
                TextColumn::make('period')->label('সময়')->state(fn (Bill $record) => $record->period_from->format('j M').' – '.$record->period_to->format('j M')),
                TextColumn::make('orders_count')->label('বক্স'),
                TextColumn::make('total_paisa')->label('মোট')->formatStateUsing(fn (int $state) => self::taka($state))->sortable(),
                TextColumn::make('due')->label('বাকি')->state(fn (Bill $record) => self::taka($record->duePaisa()))->weight('semibold'),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn (string $state) => Bill::STATUS_LABELS[$state] ?? $state)->color(fn (string $state) => self::statusColor($state)),
                TextColumn::make('created_at')->label('তৈরি')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(Bill::STATUS_LABELS)
                    ->default(null),
            ])
            ->recordActions([
                ViewAction::make(),
                self::payAction(),
                self::whatsappAction(),
            ]);
    }

    public static function canCreate(): bool
    {
        // Bills are made from a customer ("বিল তৈরি করুন") or by the Saturday / 1st run.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBills::route('/'),
            'view' => ViewBill::route('/{record}'),
        ];
    }
}
