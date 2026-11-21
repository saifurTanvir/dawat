<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BaddaAdsReport;
use App\Models\Order;
use App\Services\Ads\BaddaAds;
use App\Support\AdminFeatures;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** F93: one line per Badda ad on top of the dashboard; the page has the details. */
class BaddaAdsSummary extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return AdminFeatures::allows(BaddaAdsReport::class);
    }

    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'বাড্ডা অ্যাড — আজকের হিসাব';

    protected function getStats(): array
    {
        $bn = fn ($n) => Order::banglaDigits((string) $n);

        return collect(app(BaddaAds::class)->report())->map(function (array $a) use ($bn) {
            $result = $a['results'][1] ?? $a['results'][0];

            return Stat::make($a['label'].' অ্যাড', $bn($a['total']).' জন এসেছেন')
                ->description('উত্তর '.$bn($a['answered']).' · Messenger '.$bn($a['messenger']).' · WhatsApp '.$bn($a['whatsapp']).' · কমেন্ট '.$bn($a['comments']).' · '.$result[0].' '.$bn($result[1]))
                ->color($a['answered'] < $a['total'] ? 'warning' : 'success')
                ->url(BaddaAdsReport::getUrl());
        })->values()->all();
    }
}
