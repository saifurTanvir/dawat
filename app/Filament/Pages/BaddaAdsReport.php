<?php

namespace App\Filament\Pages;

use App\Filament\Support\FeatureGate;
use App\Services\Ads\BaddaAds;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/** F93: the three Badda ads, side by side — counted fresh every time the page opens. */
class BaddaAdsReport extends Page
{
    use FeatureGate;

    protected string $view = 'filament.pages.badda-ads-report';

    protected static ?string $slug = 'badda-ads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'বাড্ডা অ্যাডের রিপোর্ট';

    protected static ?string $title = 'বাড্ডা অ্যাডের রিপোর্ট';

    protected static ?int $navigationSort = -10;

    protected ?string $subheading = 'তিনটা বাড্ডা অ্যাড (২১ নভেম্বর বিকেল থেকে): কে কোন পথে এলেন — Messenger, WhatsApp, কমেন্ট — আমরা উত্তর দিয়েছি কিনা, আর কতজন যোগ হলেন। পাতা খুললেই নতুন হিসাব।';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncComments')->label('কমেন্ট এখনই আনুন')->icon(Heroicon::OutlinedArrowPath)
                ->action(function () {
                    Artisan::call('ads:sync-comments');
                    Notification::make()->title(Cache::get('ads:comments-error') ? 'কমেন্ট আনা যায়নি' : 'কমেন্ট হালনাগাদ হলো')->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'ads' => app(BaddaAds::class)->report(),
            'commentsError' => Cache::get('ads:comments-error'),
            'commentsSynced' => Cache::get('ads:comments-synced'),
        ];
    }
}
