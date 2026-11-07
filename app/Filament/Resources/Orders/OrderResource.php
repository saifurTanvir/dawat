<?php

namespace App\Filament\Resources\Orders;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Services\Orders\BookingPlanner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer.user', 'kitchen', 'shahidar']))
            ->defaultSort('seq', 'desc')
            ->columns([
                TextColumn::make('seq')->label('বক্স')->state(fn (Order $record) => $record->code())->weight('semibold'),
                TextColumn::make('meal_slot')->label('বেলা'),
                TextColumn::make('customer.user.name')->label('গ্রাহক')->searchable(),
                TextColumn::make('kitchen.display_name')->label('গৃহিণী'),
                TextColumn::make('shahidar.name')->label('শাহিদার'),
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
                    ->indicateUsing(fn (array $data) => ($data['date'] ?? null) ? 'তারিখ: '.$data['date'] : null),
                SelectFilter::make('status')->label('অবস্থা')->options(OrderStatus::class),
                SelectFilter::make('meal_slot')->label('বেলা')->options(MealSlot::class),
                Filter::make('unverified')->label('যাচাই বাকি (কোড/স্ক্যান ছাড়া)')
                    ->query(fn (Builder $query) => $query->where('confirm_method', 'shahidar_tap')),
                SelectFilter::make('shahidar_id')->label('শাহিদার')->relationship('shahidar', 'name'),
                SelectFilter::make('kitchen_id')->label('গৃহিণী')->relationship('kitchen', 'display_name'),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('আজ বাদ')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('warning')
                    ->visible(fn (Order $record) => $record->status === OrderStatus::Booked)
                    ->schema([TextInput::make('reason')->label('কারণ')->required()->maxLength(200)->default('গ্রাহক ফোনে জানিয়েছেন')])
                    ->action(function (Order $record, array $data) {
                        app(BookingPlanner::class)->cancel($record, 'admin', $data['reason']);
                        Notification::make()->title('বাদ দেওয়া হয়েছে, প্লেট ফিডে ফিরেছে')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}
