<?php

namespace App\Filament\Widgets;

use App\Enums\ContactType;
use App\Enums\KitchenStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\ContactEvent;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Support\DhakaTime;
use App\Support\Roles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** F23: today at a glance, in Dhaka time. */
class DawatStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = DhakaTime::startOfDay();

        $contacts = ContactEvent::where('created_at', '>=', $today)
            ->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        $count = fn (ContactType $t) => (int) ($contacts[$t->value] ?? 0);

        $lastWeek = collect(range(6, 0))->map(fn ($d) => ContactEvent::whereBetween('created_at', [DhakaTime::startOfDay($d), DhakaTime::startOfDay($d - 1)])->count())->all();

        $waitingKitchens = Kitchen::where('status', KitchenStatus::Pending)->count();
        $waitingNumbers = Kitchen::where('pending_contact_method', 'admin')->count();
        $openReports = Report::where('status', ReportStatus::Open)->count();

        return [
            Stat::make('এখন চালু খাবার', Post::visibleNow()->count())
                ->description('আজ পোস্ট হয়েছে '.Post::where('created_at', '>=', $today)->count().'টি')
                ->color('success'),

            Stat::make('আজকের যোগাযোগ', $contacts->sum())
                ->description("মেসেজ {$count(ContactType::Message)} · কল {$count(ContactType::Call)} · কমেন্ট {$count(ContactType::Comment)} · শেয়ার {$count(ContactType::Share)}")
                ->chart($lastWeek)
                ->color('primary'),

            Stat::make('আজ নতুন ইউজার', User::where('created_at', '>=', $today)->count())
                ->description('মোট রাঁধুনি '.User::role(Roles::COOK)->count().' জন'),

            Stat::make('অনুমোদনের অপেক্ষায়', $waitingKitchens + $waitingNumbers)
                ->description("রান্নাঘর {$waitingKitchens} · নম্বর {$waitingNumbers}")
                ->color($waitingKitchens + $waitingNumbers > 0 ? 'warning' : 'gray')
                ->url(KitchenResource::getUrl('index')),

            Stat::make('খোলা রিপোর্ট', $openReports)
                ->color($openReports > 0 ? 'danger' : 'gray')
                ->url(ReportResource::getUrl('index')),
        ];
    }
}
