<?php

namespace App\Services\Kitchens;

use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Models\User;
use App\Services\Geo\FocusSplit;
use App\Services\Geo\LaunchZones;
use App\Support\PhoneNumber;
use App\Support\PinReach;
use Illuminate\Support\Collection;

/**
 * F93: cooks of a zone (Badda by default) still without a home pin, for the
 * owner to call one by one: name, number, WhatsApp link, plates, the last
 * remark, and her own location link (reused while valid, else a fresh 7-day
 * one — never anyone else's).
 */
final class PinlessCallList
{
    public const HEADERS = ['#', 'রান্নাঘর', 'গৃহিণী', 'মোবাইল', 'WhatsApp', 'এলাকা', 'দিনে প্লেট', 'অবস্থা', 'Messenger', 'শেষ রিমার্ক', 'তাঁর লোকেশন লিংক', 'লিংকের মেয়াদ', 'রিমার্কস (কলের পর লিখুন)'];

    /** @return Collection<int, Kitchen> */
    public function kitchens(string $zone = FocusSplit::FOCUS): Collection
    {
        $ids = Area::query()->whereIn('name', LaunchZones::GROUPS[$zone] ?? [])->pluck('id');

        return Kitchen::query()->whereIn('area_id', $ids)->whereNull('location')
            ->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])
            ->with(['user.remarks', 'area'])
            ->orderBy('area_id')->orderBy('id')->get();
    }

    /** @return list<list<string>> rows without the header */
    public function rows(?User $by, string $zone = FocusSplit::FOCUS): array
    {
        $kitchens = $this->kitchens($zone);
        $reach = PinReach::of($kitchens);
        $rows = [];
        foreach ($kitchens->values() as $i => $k) {
            $phone = (string) $k->user?->phone;
            $link = PinLink::forKitchen($k, $by);
            $remark = $k->user?->remarks->first();
            $rows[] = [
                (string) ($i + 1),
                (string) $k->display_name,
                (string) $k->user?->name,
                $phone,
                $phone !== '' ? 'https://wa.me/'.PhoneNumber::international($phone) : '',
                (string) $k->area?->name,
                $k->daily_plates ? $k->daily_plates.' প্লেট' : 'জানা নেই',
                $k->status === KitchenStatus::Approved ? 'অনুমোদিত' : 'অপেক্ষায়',
                PinReach::LABELS[$reach[$k->id]['reach'] ?? 'none'],
                $remark ? mb_substr($remark->body, 0, 200) : '',
                $link->url(),
                $link->expires_at->timezone(config('app.display_timezone'))->format('j M'),
                '',
            ];
        }

        return $rows;
    }
}
