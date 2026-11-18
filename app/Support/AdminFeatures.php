<?php

namespace App\Support;

use App\Filament\Pages\CookFeesPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DeliveryQuality;
use App\Filament\Pages\ImportPeople;
use App\Filament\Pages\Money;
use App\Filament\Pages\ZoneMap;
use App\Filament\PageWidgets\DeliveryQualityTable;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\CallRequests\CallRequestResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\QrCodes\QrCodeResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\ShahidarApplications\ShahidarApplicationResource;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Filament\Resources\Stickers\StickerResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\CallList;
use App\Filament\Widgets\DailyActivityChart;
use App\Filament\Widgets\DawatStats;
use App\Filament\Widgets\FollowUps;
use App\Filament\Widgets\GuestMealRequests;
use App\Filament\Widgets\MealRequests;
use App\Filament\Widgets\PinReviews;
use App\Filament\Widgets\SmsProblems;
use App\Filament\Widgets\TodayPlates;
use App\Filament\Widgets\TopKitchens;
use App\Filament\Widgets\TopQrCodes;
use App\Models\User;

/**
 * F69: the admin panel split into features. An admin makes staff roles from
 * these (e.g. "কল টিম" = যোগাযোগ ও ফলো-আপ) and gives them to staff, who see
 * only those parts. Admins see everything. Anything not listed here —
 * areas, staff and roles, login links, "view as", deleting — is admins' only.
 */
final class AdminFeatures
{
    /** key => [label, what it opens] */
    public const FEATURES = [
        'contacts' => ['যোগাযোগ ও ফলো-আপ', 'যোগাযোগ করতে হবে, আজকের ফলো-আপ, রিমার্কস'],
        'kitchens' => ['রান্নাঘর ও পোস্ট', 'রান্নাঘর, পোস্ট, ট্যাগ, জোনের হিসাব, পিন যাচাই'],
        'customers' => ['নিয়মিত গ্রাহক ও অফিস', 'নিয়মিত গ্রাহক, অফিস, অন্য খাবার ও অতিথির খাবারের অনুরোধ'],
        'orders' => ['অর্ডার ও ডেলিভারি', 'অর্ডার (বক্স), বক্স স্টিকার, ডেলিভারির হিসাব, আজকের প্লেট'],
        'shahidars' => ['শাহিদার', 'শাহিদার, শাহিদার আবেদন'],
        'money' => ['টাকা ও বিল', 'টাকার হিসাব, বিল, গৃহিণীর ফি'],
        'import' => ['নতুন মানুষ যোগ', 'তালিকা থেকে যোগ, ড্যাশবোর্ডের দ্রুত যোগের বাটন'],
        'reports' => ['রিপোর্ট ও ইউজার', 'রিপোর্ট, ইউজার, কোড না আসার সমস্যা'],
        'stats' => ['পরিসংখ্যান ও QR', 'ড্যাশবোর্ডের সংখ্যা ও চার্ট, QR কোড'],
    ];

    /** Panel page/resource/widget class => feature key. */
    public const PARTS = [
        CallRequestResource::class => 'contacts',
        CallList::class => 'contacts',
        FollowUps::class => 'contacts',
        KitchenResource::class => 'kitchens',
        PostResource::class => 'kitchens',
        TagResource::class => 'kitchens',
        ZoneMap::class => 'kitchens',
        PinReviews::class => 'kitchens',
        TopKitchens::class => 'kitchens',
        CustomerResource::class => 'customers',
        CompanyResource::class => 'customers',
        MealRequests::class => 'customers',
        GuestMealRequests::class => 'customers',
        OrderResource::class => 'orders',
        StickerResource::class => 'orders',
        DeliveryQuality::class => 'orders',
        DeliveryQualityTable::class => 'orders',
        TodayPlates::class => 'orders',
        ShahidarResource::class => 'shahidars',
        ShahidarApplicationResource::class => 'shahidars',
        Money::class => 'money',
        BillResource::class => 'money',
        CookFeesPage::class => 'money',
        ImportPeople::class => 'import',
        ReportResource::class => 'reports',
        UserResource::class => 'reports',
        SmsProblems::class => 'reports',
        DawatStats::class => 'stats',
        DailyActivityChart::class => 'stats',
        TopQrCodes::class => 'stats',
        QrCodeResource::class => 'stats',
    ];

    public const PREFIX = 'feature:';

    /** May the signed-in person open this part of the panel? */
    public static function allows(string $class, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User || $user->isBlocked()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if ($class === Dashboard::class) {
            return $user->isStaff();
        }
        $key = self::PARTS[$class] ?? null;

        return $key !== null && $user->canUseFeature($key);
    }

    /** @return array<string, string> key => "label — what it opens", for checkboxes */
    public static function options(): array
    {
        return collect(self::FEATURES)->map(fn (array $f) => $f[0])->all();
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return collect(self::FEATURES)->map(fn (array $f) => $f[1])->all();
    }
}
