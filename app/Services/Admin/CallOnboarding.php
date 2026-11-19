<?php

namespace App\Services\Admin;

use App\Actions\EnsurePerson;
use App\Enums\CallRequestStatus;
use App\Filament\Actions\QuickAddActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * F70: when a call on "যোগাযোগ করতে হবে" is finished, the person should be
 * in the system — as a cook, a regular customer or a shahidar, or at least
 * with an account. Already in → nothing to do. Otherwise the closer picks
 * what to add (guessed from the reason), or says "এখন না".
 */
class CallOnboarding
{
    public const ROLES = [
        'cook' => 'গৃহিণী (রাঁধুনি)',
        'customer' => 'নিয়মিত গ্রাহক',
        'shahidar' => 'শাহিদার',
        'account' => 'শুধু অ্যাকাউন্ট (অ্যাপে ঢুকতে পারবেন)',
        'skip' => 'এখন যোগ করব না',
    ];

    public const RESULTS = [
        'cook' => 'গৃহিণী হিসেবে যোগ হয়েছে',
        'customer' => 'গ্রাহক হিসেবে যোগ হয়েছে',
        'shahidar' => 'শাহিদার হিসেবে যোগ হয়েছে',
        'account' => 'অ্যাকাউন্ট খোলা হয়েছে',
        'existing' => 'আগে থেকেই সিস্টেমে',
        'skip' => 'যোগ করা হয়নি',
    ];

    private const WORDS = [
        'shahidar' => ['শাহিদার', 'সাহিদার', 'ডেলিভারি দিতে', 'ডেলিভারির কাজ', 'ডেলিভারি ম্যান', 'রাইডার', 'বাইক', 'সাইকেল', 'rider', 'delivery'],
        'cook' => ['রান্না', 'রাঁধ', 'রাধ', 'গৃহিণী', 'গৃহিনী', 'রাঁধুনি', 'রাধুনি', 'kitchen', 'cook'],
        'customer' => ['খাবার নিতে', 'খাবার নেব', 'খাবার চাই', 'খেতে', 'নিয়মিত খাবার', 'লাঞ্চ', 'টিফিন', 'অর্ডার দিতে', 'গ্রাহক', 'কাস্টমার'],
    ];

    /** People who are a cook, a regular customer or a shahidar. */
    public static function inSystem(): Builder
    {
        return User::query()->where(fn ($q) => $q->has('kitchen')->orHas('customer')
            ->orWhereHas('roles', fn ($r) => $r->where('name', Roles::SHAHIDAR)));
    }

    /** Finished calls whose person is not in the system yet, and nobody said "এখন না". */
    public static function pending(): Builder
    {
        return CallRequest::query()
            ->where('status', CallRequestStatus::Done)
            ->whereNull('onboard_result')
            ->whereNotIn('phone', self::inSystem()->select('phone'));
    }

    /** "গৃহিণী — রহিমা খালার রান্নাঘর", or null when not in the system. */
    public static function existing(CallRequest $r): ?string
    {
        $user = User::with(['kitchen', 'customer', 'roles'])->where('phone', $r->phone)->first();
        if (! $user) {
            return null;
        }
        $as = array_filter([
            $user->kitchen ? 'গৃহিণী — '.$user->kitchen->display_name : null,
            $user->customer ? 'নিয়মিত গ্রাহক' : null,
            $user->isShahidar() ? 'শাহিদার' : null,
        ]);

        return $as ? implode(', ', $as) : null;
    }

    public static function guessRole(CallRequest $r): string
    {
        $text = mb_strtolower($r->reason.' '.collect($r->notes ?? [])->pluck('body')->implode(' '));
        foreach (self::WORDS as $role => $words) {
            foreach ($words as $w) {
                if (str_contains($text, mb_strtolower($w))) {
                    return $role;
                }
            }
        }

        return 'account';
    }

    public static function guessArea(CallRequest $r): ?int
    {
        $text = $r->reason.' '.collect($r->notes ?? [])->pluck('body')->implode(' ');
        // Longest names first, so "মিরপুর ১০" wins over "মিরপুর".
        foreach (Area::active()->get()->sortByDesc(fn (Area $a) => mb_strlen($a->name)) as $area) {
            if (str_contains($text, $area->name)) {
                return $area->id;
            }
        }

        return null;
    }

    /**
     * Adds the person as chosen and notes it on the call.
     *
     * @param  array<string, mixed>  $data  role, name, area_id(s), display_name, pickup_address, address, meal_slots, plates
     */
    public function onboard(CallRequest $r, array $data): string
    {
        return DB::transaction(function () use ($r, $data) {
            if (self::existing($r)) {
                $r->forceFill(['onboard_result' => 'existing', 'onboarded_at' => now()])->save();

                return 'existing';
            }
            $role = $data['role'] ?? 'skip';
            $name = trim((string) ($data['name'] ?? $r->name ?? '')) ?: null;

            match ($role) {
                'cook' => QuickAddActions::createCook([
                    'phone' => $r->phone, 'name' => $name, 'area_id' => $data['area_id'],
                    'display_name' => trim((string) ($data['display_name'] ?? '')) ?: ($name ? $name.'-এর রান্নাঘর' : 'নতুন রান্নাঘর'),
                    'pickup_address' => $data['pickup_address'] ?? null,
                ]),
                'customer' => CustomerResource::createCustomer([
                    'phone' => $r->phone, 'name' => $name, 'area_id' => $data['area_id'], 'address' => $data['address'],
                    'meal_slots' => $data['meal_slots'] ?? ['lunch'], 'plates' => $data['plates'] ?? 1,
                    'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'billing' => 'each', 'note' => null,
                ]),
                'shahidar' => ShahidarResource::createShahidar(['phone' => $r->phone, 'name' => $name, 'area_ids' => (array) ($data['area_ids'] ?? [])]),
                'account' => app(EnsurePerson::class)($r->phone, $name, Roles::BUYER),
                default => null,
            };

            $r->forceFill(['onboard_result' => $role, 'onboarded_at' => now()]);
            $r->save(); // re-links user_id to the new account

            return $role;
        });
    }
}
