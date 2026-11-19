<?php

namespace App\Filament\Pages;

use App\Enums\KitchenStatus;
use App\Filament\Support\FeatureGate;
use App\Models\Kitchen;
use App\Models\OpsHeartbeat;
use App\Services\Geo\Zones;
use App\Support\DhakaTime;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * F78: is the Messenger / WhatsApp reply loop working right now? Each loop checks in
 * (POST /admin-tools/heartbeat); a loop silent for 30 minutes shows as stopped.
 * Admins only (not in AdminFeatures::PARTS). Refreshes itself every minute.
 */
class OpsStatus extends Page
{
    use FeatureGate;

    protected string $view = 'filament.pages.ops-status';

    protected static ?string $slug = 'status';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'উত্তরের অবস্থা';

    protected static ?string $title = 'উত্তর দেওয়ার কাজ চলছে কিনা';

    protected static ?int $navigationSort = -1;

    protected ?string $subheading = 'Messenger আর WhatsApp-এ মেসেজের উত্তর দেওয়ার কাজ এখন চলছে কিনা, আজ কতজনকে উত্তর দেওয়া হলো, কতজন বাকি। পাতা নিজে থেকে প্রতি মিনিটে নতুন হয়।';

    protected function getViewData(): array
    {
        $today = DhakaTime::startOfDay();

        $channels = collect(OpsHeartbeat::CHANNELS)->map(function (string $label, string $key) use ($today) {
            $last = OpsHeartbeat::query()->where('channel', $key)->latest('id')->first();
            $state = match (true) {
                $last === null => 'none',
                $last->created_at->lt(now()->subMinutes(OpsHeartbeat::STALE_MINUTES)) => 'stopped',
                $last->problem => 'problem',
                default => 'ok',
            };

            return [
                'label' => $label,
                'state' => $state,
                'last' => $last,
                'sentToday' => (int) OpsHeartbeat::query()->where('channel', $key)->where('created_at', '>=', $today)->sum('sent'),
            ];
        });

        $approved = Kitchen::query()->where('status', KitchenStatus::Approved);

        return [
            'channels' => $channels,
            'recent' => OpsHeartbeat::query()->latest('id')->limit(15)->get(),
            'kitchensToday' => Kitchen::query()->where('created_at', '>=', $today)->count(),
            'kitchensTotal' => (clone $approved)->count(),
            'pinless' => (clone $approved)->whereNull('location')->count(),
            'platesKnown' => (clone $approved)->whereNotNull('daily_plates')->count(),
            'bn' => fn ($n) => Zones::bn($n),
        ];
    }
}
