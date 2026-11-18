<?php

namespace App\Filament\PageWidgets;

use App\Enums\OrderStatus;
use App\Models\User;
use App\Services\Orders\Delivery;
use App\Support\AdminFeatures;
use App\Support\DhakaTime;
use App\Support\Roles;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * F67: how each shahidar picks up and hands over — boxes picked without a
 * sticker, and deliveries confirmed by the customer, by code or scan, or by
 * a plain tap that nobody can check.
 */
class DeliveryQualityTable extends TableWidget
{
    /** F69: admins, and staff with this part's feature. */
    public static function canView(): bool
    {
        return AdminFeatures::allows(static::class);
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'শাহিদার ধরে পিকআপ ও ডেলিভারি';

    public const PERIODS = [1 => 'আজ', 7 => 'শেষ ৭ দিন', 30 => 'শেষ ৩০ দিন'];

    public static function since(int $days)
    {
        return $days === 1 ? DhakaTime::startOfDay() : DhakaTime::startOfDay($days - 1);
    }

    public static function withCounts(Builder $query, int $days): Builder
    {
        $since = self::since($days);
        $picked = fn ($q) => $q->where('picked_at', '>=', $since);
        $delivered = fn ($q) => $q->where('status', OrderStatus::Delivered)->where('delivered_at', '>=', $since);

        return $query->withCount([
            'shahidarOrders as picked' => $picked,
            'shahidarOrders as picked_bare' => fn ($q) => $picked($q)->doesntHave('sticker'),
            'shahidarOrders as delivered' => $delivered,
            'shahidarOrders as by_customer' => fn ($q) => $delivered($q)->whereIn('confirm_method', [Delivery::BY_CUSTOMER_SCAN, Delivery::BY_CUSTOMER_CODE]),
            'shahidarOrders as by_check' => fn ($q) => $delivered($q)->whereIn('confirm_method', [Delivery::BY_SHAHIDAR_CODE, Delivery::BY_SHAHIDAR_SCAN]),
            'shahidarOrders as by_tap' => fn ($q) => $delivered($q)->where('confirm_method', Delivery::BY_SHAHIDAR_TAP),
            'shahidarOrders as failed' => fn ($q) => $q->where('status', OrderStatus::Failed)->where('updated_at', '>=', $since),
        ]);
    }

    private static function share(int $part, int $whole): string
    {
        return $whole ? round(100 * $part / $whole).'%' : '';
    }

    public function table(Table $table): Table
    {
        return $table
            // The period filter only sets the counts; it hides nobody.
            ->query(fn () => self::withCounts(User::role(Roles::SHAHIDAR), (int) ($this->tableFilters['days']['value'] ?? 7) ?: 7))
            ->defaultSort('picked', 'desc')
            ->paginated([25])
            ->filters([
                SelectFilter::make('days')->label('সময়')->options(self::PERIODS)->default(7)->selectablePlaceholder(false)
                    ->query(fn (Builder $query) => $query),
            ])
            ->columns([
                TextColumn::make('name')->label('শাহিদার')->weight('semibold')
                    ->formatStateUsing(fn ($state, User $u) => $state ?: $u->phone)->description(fn (User $u) => $u->phone),
                TextColumn::make('picked')->label('পিকআপ')->sortable(),
                TextColumn::make('picked_bare')->label('স্টিকার ছাড়া পিকআপ')->sortable()
                    ->description(fn (User $u) => self::share($u->picked_bare, $u->picked))
                    ->color(fn (User $u) => $u->picked_bare ? 'warning' : null),
                TextColumn::make('delivered')->label('পৌঁছেছে')->sortable(),
                TextColumn::make('by_customer')->label('গ্রাহক নিজে নিশ্চিত')->sortable()
                    ->description(fn (User $u) => self::share($u->by_customer, $u->delivered))->color('success'),
                TextColumn::make('by_check')->label('কোড / স্টিকার স্ক্যানে')->sortable()
                    ->description(fn (User $u) => self::share($u->by_check, $u->delivered)),
                TextColumn::make('by_tap')->label('কোড ছাড়া (যাচাই বাকি)')->sortable()
                    ->description(fn (User $u) => self::share($u->by_tap, $u->delivered))
                    ->color(fn (User $u) => $u->by_tap ? 'danger' : null),
                TextColumn::make('failed')->label('পৌঁছানো যায়নি')->sortable(),
            ]);
    }
}
