<?php

namespace App\Filament\Support;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Reviews\BoxReviews;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * F49: one table for a person's boxes (cook, shahidar, customer, buyer): the
 * last 30 days by default, every name a link, each row opens the box.
 */
final class OrderHistoryTable
{
    /** @param  list<string>  $hide  columns that would repeat the owner: kitchen | shahidar | recipient */
    public static function configure(Table $table, array $hide = []): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer.user', 'buyer', 'kitchen', 'shahidar', 'bill', 'review']))
            ->defaultSort('service_date', 'desc')
            ->recordUrl(fn (Order $record) => Links::order($record))
            ->columns(array_values(array_filter([
                TextColumn::make('service_date')->label('তারিখ')->date('j M Y')->sortable()
                    ->description(fn (Order $o) => $o->meal_slot->label()),
                TextColumn::make('seq')->label('বক্স')->state(fn (Order $o) => $o->code())->weight('semibold'),
                TextColumn::make('title')->label('খাবার')->placeholder('—')->limit(30),
                in_array('recipient', $hide, true) ? null : TextColumn::make('recipient')->label('গ্রাহক')
                    ->state(fn (Order $o) => $o->recipient()?->name ?: $o->recipient()?->phone)
                    ->description(fn (Order $o) => $o->isOneOff() ? 'ফিড থেকে' : 'নিয়মিত')
                    ->url(fn (Order $o) => Links::recipient($o)),
                in_array('kitchen', $hide, true) ? null : TextColumn::make('kitchen.display_name')->label('গৃহিণী')
                    ->url(fn (Order $o) => Links::kitchen($o->kitchen_id)),
                in_array('shahidar', $hide, true) ? null : TextColumn::make('shahidar.name')->label('শাহিদার')->placeholder('—')
                    ->url(fn (Order $o) => Links::shahidar($o->shahidar_id)),
                TextColumn::make('plates')->label('প্লেট'),
                TextColumn::make('total')->label('টাকা')->state(fn (Order $o) => '৳'.number_format($o->totalPaisa() / 100))
                    ->description(fn (Order $o) => BoxReviews::paymentLabel($o)),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->description(fn (Order $o) => $o->cancel_reason ?? $o->failure_reason),
                TextColumn::make('review.stars')->label('রিভিউ')->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state) : null),
            ])))
            ->filters([
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label('কবে থেকে')->native(false)->default(now()->subDays(30)->toDateString()),
                        DatePicker::make('until')->label('কবে পর্যন্ত')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('service_date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('service_date', '<=', $v)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['from'] ?? null) ? 'থেকে '.Carbon::parse($data['from'])->format('j M Y') : null,
                        ($data['until'] ?? null) ? 'পর্যন্ত '.Carbon::parse($data['until'])->format('j M Y') : null,
                    ]))),
                SelectFilter::make('status')->label('অবস্থা')->options(OrderStatus::class),
                SelectFilter::make('meal_slot')->label('বেলা')->options(MealSlot::class),
                SelectFilter::make('kind')->label('ধরন')->options(['regular' => 'নিয়মিত', 'oneoff' => 'ফিড থেকে']),
            ])
            ->headerActions([
                CsvDownload::make('boxes', [
                    'তারিখ' => fn (Order $o) => $o->service_date->toDateString(),
                    'বেলা' => fn (Order $o) => $o->meal_slot->label(),
                    'বক্স' => fn (Order $o) => $o->code(),
                    'খাবার' => fn (Order $o) => $o->title,
                    'ধরন' => fn (Order $o) => $o->isOneOff() ? 'ফিড থেকে' : 'নিয়মিত',
                    'গ্রাহক' => fn (Order $o) => $o->recipient()?->name,
                    'ফোন' => fn (Order $o) => $o->recipient()?->phone,
                    'গৃহিণী' => fn (Order $o) => $o->kitchen?->display_name,
                    'শাহিদার' => fn (Order $o) => $o->shahidar?->name,
                    'প্লেট' => fn (Order $o) => $o->plates,
                    'খাবারের দাম' => fn (Order $o) => intdiv($o->food_paisa, 100),
                    'ডেলিভারি' => fn (Order $o) => intdiv($o->delivery_fee_paisa, 100),
                    'অবস্থা' => fn (Order $o) => $o->status->label(),
                    'কারণ' => fn (Order $o) => $o->cancel_reason ?? $o->failure_reason,
                    'টাকা' => fn (Order $o) => BoxReviews::paymentLabel($o),
                    'রিভিউ' => fn (Order $o) => $o->review?->stars,
                ], ['customer.user', 'buyer', 'kitchen', 'shahidar', 'bill', 'review']),
            ])
            ->paginated([25, 50, 100]);
    }
}
