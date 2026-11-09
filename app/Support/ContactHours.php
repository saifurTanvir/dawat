<?php

namespace App\Support;

use App\Enums\MealSlot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * F41: whether a person may be called right now, and a free way to reach
 * them (WhatsApp) instead of paid SMS.
 *
 * Each person picks their own hours (within 7 am – 10 pm). Dawat fixes one
 * thing they cannot close: on a day they have a box or a cooking, that
 * meal's delivery window stays open for the shahidar and Dawat.
 */
final class ContactHours
{
    public const PREFER_LABELS = ['any' => 'ফোন বা হোয়াটসঅ্যাপ', 'call' => 'ফোন কল', 'whatsapp' => 'শুধু হোয়াটসঅ্যাপ মেসেজ'];

    /** @return array{0: string, 1: string} HH:MM from, to */
    public static function hours(User $user): array
    {
        [$from, $to] = config('dawat.contact.default_hours');

        return [
            $user->call_from ? substr((string) $user->call_from, 0, 5) : $from,
            $user->call_to ? substr((string) $user->call_to, 0, 5) : $to,
        ];
    }

    /** @return array{0: string, 1: string}|null */
    public static function mealWindow(?MealSlot $slot): ?array
    {
        return $slot ? (config('dawat.contact.meal_windows')[$slot->value] ?? null) : null;
    }

    /** @param  list<MealSlot>  $slotsToday  meals this person has a box or cooking for today */
    public static function okNow(User $user, array $slotsToday = [], ?Carbon $now = null): bool
    {
        return self::reason($user, $slotsToday, $now) !== null;
    }

    /** Why a call is fine now ('hours' or 'meal'), or null when it is not. */
    public static function reason(User $user, array $slotsToday = [], ?Carbon $now = null): ?string
    {
        $hm = ($now ?? now())->copy()->timezone(DhakaTime::TZ)->format('H:i');
        [$from, $to] = self::hours($user);
        if (self::between($hm, $from, $to)) {
            return 'hours';
        }
        foreach ($slotsToday as $slot) {
            $w = self::mealWindow($slot);
            if ($w && self::between($hm, $w[0], $w[1])) {
                return 'meal';
            }
        }

        return null;
    }

    private static function between(string $hm, string $from, string $to): bool
    {
        return $from <= $to ? ($hm >= $from && $hm < $to) : ($hm >= $from || $hm < $to);
    }

    /** "সকাল ৯টা – রাত ৯টা" */
    public static function label(User $user): string
    {
        [$from, $to] = self::hours($user);

        return self::clock($from).' – '.self::clock($to);
    }

    /** "13:30" → "দুপুর ১:৩০" */
    public static function clock(string $hm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hm));
        $part = match (true) {
            $h < 5 => 'রাত',
            $h < 12 => 'সকাল',
            $h < 15 => 'দুপুর',
            $h < 18 => 'বিকেল',
            $h < 19 => 'সন্ধ্যা',
            default => 'রাত',
        };
        $h12 = $h % 12 ?: 12;

        return $part.' '.Order::banglaDigits($m ? sprintf('%d:%02d', $h12, $m) : $h12.'টা');
    }

    public static function whatsappUrl(?string $phone, ?string $text = null): ?string
    {
        if (! $phone) {
            return null;
        }

        return 'https://wa.me/'.PhoneNumber::international($phone).($text ? '?text='.rawurlencode($text) : '');
    }

    /**
     * What a shahidar or admin needs to reach someone, for API rows.
     *
     * @param  list<MealSlot>  $slotsToday
     */
    public static function card(User $user, array $slotsToday = [], ?string $whatsappText = null): array
    {
        $reason = self::reason($user, $slotsToday);

        return [
            'ok_now' => $reason !== null,
            'why' => $reason === 'meal' ? 'ডেলিভারির সময়' : null,
            'hours' => self::label($user),
            'prefer' => $user->contact_prefer ?: 'any',
            'whatsapp_url' => self::whatsappUrl($user->phone, $whatsappText),
        ];
    }

    /** For the admin panel: "এখন কল করা যাবে · সকাল ৯টা – রাত ৯টা". */
    public static function adminLine(User $user, array $slotsToday = []): string
    {
        $reason = self::reason($user, $slotsToday);
        $now = match ($reason) {
            'hours' => '✅ এখন কল করা যাবে',
            'meal' => '✅ এখন কল করা যাবে (ডেলিভারির সময়)',
            default => '⛔ এখন কল নয়, হোয়াটসঅ্যাপে মেসেজ দিন',
        };
        $prefer = ($user->contact_prefer ?: 'any') === 'any' ? '' : ' · পছন্দ: '.self::PREFER_LABELS[$user->contact_prefer];

        return $now.' · '.self::label($user).$prefer;
    }

    /** @return array{from: string, to: string, prefer: string, label: string, meal_windows: list<array{slot: string, label: string}>} */
    public static function forMe(User $user): array
    {
        [$from, $to] = self::hours($user);

        return [
            'from' => $from,
            'to' => $to,
            'prefer' => $user->contact_prefer ?: 'any',
            'label' => self::label($user),
            'earliest' => config('dawat.contact.earliest'),
            'latest' => config('dawat.contact.latest'),
            'meal_windows' => collect(config('dawat.contact.meal_windows'))->map(fn ($w, $slot) => [
                'slot' => $slot,
                'label' => MealSlot::from($slot)->label().': '.self::clock($w[0]).' – '.self::clock($w[1]),
            ])->values()->all(),
        ];
    }
}
