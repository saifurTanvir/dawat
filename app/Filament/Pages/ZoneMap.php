<?php

namespace App\Filament\Pages;

use App\Enums\KitchenStatus;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Services\Geo\Zones;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** F67: 1 km zones over every area — cooks, customers, shahidars in each. */
class ZoneMap extends Page
{
    protected string $view = 'filament.pages.zone-map';

    protected static ?string $slug = 'zones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'রাঁধুনি ও খাবার';

    protected static ?string $navigationLabel = 'জোনের হিসাব';

    protected static ?string $title = 'জোনের হিসাব (১ কিমি বৃত্ত)';

    protected static ?int $navigationSort = 4;

    /** Show only zones/areas that have someone in them. */
    public bool $onlyBusy = true;

    /** F68: list cooks without a home pin, with their links. */
    public bool $showPinLinks = false;

    protected function getViewData(): array
    {
        $report = app(Zones::class)->report();
        $areas = $report['areas'];
        if ($this->onlyBusy) {
            $areas = $areas->filter(fn ($a) => $a['cooks'] + $a['pending'] + $a['customers'] > 0)->values();
        }

        $map = $report['areas']->flatMap(fn ($a) => $a['zones'])->map(fn ($z) => [
            'c' => [round($z['center']->lat, 5), round($z['center']->lng, 5)],
            'n' => $z['name'],
            'k' => $z['cooks'],
            'p' => $z['pending'],
            'u' => $z['customers'],
        ])->values();

        $pinless = $this->showPinLinks
            ? Kitchen::query()->whereNull('location')->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->with(['user', 'area'])->orderBy('id')->get()
                ->map(fn (Kitchen $k) => ['kitchen' => $k, 'link' => PinLink::forKitchen($k, auth()->user())])
            : collect();

        return ['pinless' => $pinless, 'areas' => $areas, 'totals' => $report['totals'], 'mapData' => $map, 'radius' => Zones::RADIUS_M];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pins')
                ->label(fn () => $this->showPinLinks ? 'লোকেশন লিংক লুকান' : 'পিন ছাড়া গৃহিণী ও লিংক')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('warning')
                ->action(fn () => $this->showPinLinks = ! $this->showPinLinks),
            Action::make('toggle')
                ->label(fn () => $this->onlyBusy ? 'সব এলাকা দেখুন' : 'শুধু যেখানে কেউ আছে')
                ->color('gray')
                ->action(fn () => $this->onlyBusy = ! $this->onlyBusy),
            // F68: links for every cook without a home pin, to send on WhatsApp/Messenger.
            Action::make('pinLinks')
                ->label('পিন ছাড়াদের লোকেশন লিংক (CSV)')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('warning')
                ->action(function (): StreamedResponse {
                    $kitchens = Kitchen::query()->whereNull('location')->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->with(['user', 'area'])->get();
                    $by = auth()->user();

                    return response()->streamDownload(function () use ($kitchens, $by) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['রান্নাঘর', 'গৃহিণী', 'মোবাইল', 'এলাকা', 'লিংক', 'মেসেজ']);
                        foreach ($kitchens as $k) {
                            $link = PinLink::forKitchen($k, $by);
                            fputcsv($out, [$k->display_name, $k->user?->name, $k->user?->phone, $k->area?->name, $link->url(), $link->message()]);
                        }
                        fclose($out);
                    }, 'dawat-pin-links-'.now(config('app.display_timezone'))->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            Action::make('csv')
                ->label('CSV নামান')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $report = app(Zones::class)->report();

                    return response()->streamDownload(function () use ($report) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['এলাকা (মূল ঠিকানা)', 'জোন', 'lat', 'lng', 'গৃহিণী (অনুমোদিত)', 'গৃহিণী (অপেক্ষায়)', 'গ্রাহক', 'এলাকার শাহিদার']);
                        foreach ($report['areas'] as $a) {
                            foreach ($a['zones'] as $z) {
                                fputcsv($out, [$a['name'], $z['name'], $z['center']->lat, $z['center']->lng, $z['cooks'], $z['pending'], $z['customers'], $a['shahidars']]);
                            }
                            $u = $a['unpinned'];
                            if ($u['cooks'] + $u['pending'] + $u['customers'] > 0) {
                                fputcsv($out, [$a['name'], $a['name'].' — পিন ছাড়া', '', '', $u['cooks'], $u['pending'], $u['customers'], $a['shahidars']]);
                            }
                        }
                        fclose($out);
                    }, 'dawat-zones-'.now(config('app.display_timezone'))->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
