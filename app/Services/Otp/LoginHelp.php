<?php

namespace App\Services\Otp;

use App\Enums\UserStatus;
use App\Models\LoginLink;
use App\Models\OtpCode;
use App\Models\SmsLog;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F50: getting someone in when the SMS does not arrive — a code the admin
 * sends on WhatsApp, or a one-time login link / QR. Only admins can make
 * them, never for an admin account, and every one is logged.
 */
class LoginHelp
{
    public const LINK_MINUTES = 30;

    /** The account for this phone, created the way a first sign-in would. */
    public function userFor(string $phone): User
    {
        $phone = PhoneNumber::normalize($phone) ?? throw ValidationException::withMessages(['phone' => 'নম্বরটা ঠিক নেই।']);
        $user = User::firstOrCreate(['phone' => $phone], ['status' => UserStatus::Active]);
        if ($user->wasRecentlyCreated) {
            $user->assignRole(Roles::BUYER);
        }
        if ($user->hasRole(Roles::ADMIN)) {
            throw ValidationException::withMessages(['phone' => 'অ্যাডমিন অ্যাকাউন্টের জন্য এটা করা যায় না।']);
        }

        return $user;
    }

    /** A fresh login code for the admin to send on WhatsApp. Returns the code. */
    public function whatsappCode(User $user, User $admin): string
    {
        $user = $this->userFor($user->phone);
        $length = (int) config('services.otp.length', 6);
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $admin, $code) {
            OtpCode::where('phone', $user->phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            OtpCode::create([
                'phone' => $user->phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::LINK_MINUTES),
                'channel' => 'whatsapp',
                'created_by' => $admin->id,
            ]);
            SmsLog::create(['phone' => $user->phone, 'user_id' => $user->id, 'purpose' => 'login', 'status' => 'whatsapp', 'by' => $admin->id]);
        });

        return $code;
    }

    public static function whatsappUrl(string $phone, string $text): string
    {
        return 'https://wa.me/'.PhoneNumber::international($phone).'?text='.rawurlencode($text);
    }

    /** A one-time sign-in link. Returns the full URL (the token is stored hashed). */
    public function link(User $user, User $admin): string
    {
        $user = $this->userFor($user->phone);
        $token = Str::random(48);
        LoginLink::where('user_id', $user->id)->whereNull('used_at')->update(['expires_at' => now()]);
        LoginLink::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_by' => $admin->id,
            'expires_at' => now()->addMinutes(self::LINK_MINUTES),
        ]);
        SmsLog::create(['phone' => $user->phone, 'user_id' => $user->id, 'purpose' => 'login_link', 'status' => 'whatsapp', 'by' => $admin->id]);

        return url('/l/'.$token);
    }

    /** Uses a link; returns the person or null when it is wrong, used or expired. */
    public function useLink(string $token): ?User
    {
        return DB::transaction(function () use ($token) {
            $link = LoginLink::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $link || $link->used_at || $link->expires_at->isPast()) {
                return null;
            }
            $link->forceFill(['used_at' => now()])->save();
            $user = $link->user;
            if (! $user || $user->hasRole(Roles::ADMIN)) {
                return null;
            }
            self::markVerified($user, 'link', $link->created_by);

            return $user;
        });
    }

    /** How the number was confirmed; an SMS confirmation always wins. */
    public static function markVerified(User $user, string $via, ?int $by = null): void
    {
        if ($user->verified_via === 'sms' && $via !== 'sms') {
            return;
        }
        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'verified_via' => $via,
            'verified_by' => $via === 'sms' ? null : $by,
        ])->save();
    }

    /** "SMS-এ যাচাই", "অ্যাডমিন (রাশেদ) — লগইন লিংক" … */
    public static function label(User $user): string
    {
        $by = $user->verified_by ? User::find($user->verified_by) : null;

        return match ($user->verified_via) {
            'sms' => 'SMS-এ যাচাই',
            'whatsapp' => 'SMS-এ যাচাই হয়নি — অ্যাডমিন'.($by ? " ({$by->name})" : '').' হোয়াটসঅ্যাপে কোড দিয়েছেন',
            'link' => 'SMS-এ যাচাই হয়নি — অ্যাডমিন'.($by ? " ({$by->name})" : '').' লগইন লিংক দিয়েছেন',
            default => $user->phone_verified_at ? 'যাচাই হয়েছে' : 'এখনো ঢোকেননি',
        };
    }
}
