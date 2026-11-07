<?php

namespace App\Services\Otp;

use App\Enums\UserStatus;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    /** Seconds before the same phone may ask for another code. */
    public const RESEND_AFTER = 60;

    public const MAX_PER_HOUR = 5;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly OtpSender $sender) {}

    /**
     * Creates and sends a new code.
     *
     * @throws OtpThrottledException
     * @throws OtpDeliveryFailedException
     */
    public function request(string $phone, ?string $ip = null, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
    {
        $latest = OtpCode::where('phone', $phone)->latest('id')->first();
        if ($latest && $latest->created_at->diffInSeconds(now()) < self::RESEND_AFTER) {
            throw new OtpThrottledException(self::RESEND_AFTER - (int) $latest->created_at->diffInSeconds(now()));
        }

        $sentLastHour = OtpCode::where('phone', $phone)->where('created_at', '>', now()->subHour())->count();
        if ($sentLastHour >= self::MAX_PER_HOUR) {
            throw new OtpThrottledException(3600);
        }

        $code = $this->generateCode();

        $otp = DB::transaction(function () use ($phone, $code, $ip) {
            // Only the newest code works.
            OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            return OtpCode::create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('services.otp.ttl_minutes', 5)),
                'ip' => $ip,
            ]);
        });

        try {
            $this->sender->send($phone, $code, $purpose, $context);
        } catch (OtpDeliveryFailedException $e) {
            // Not delivered: let the person ask again straight away.
            $otp->delete();
            throw $e;
        }
    }

    /**
     * Checks the code. On success returns the user, creating one on first sign-in.
     * Returns null when the code is wrong, expired or has had too many attempts.
     */
    public function verify(string $phone, string $code): ?User
    {
        return DB::transaction(function () use ($phone, $code) {
            if (! $this->consume($phone, $code)) {
                return null;
            }

            $user = User::firstOrCreate(['phone' => $phone], ['status' => UserStatus::Active]);
            if ($user->wasRecentlyCreated) {
                // Everyone starts as a buyer; becoming a cook is a separate, reviewed step.
                $user->assignRole(Roles::BUYER);
            }
            if (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $user;
        });
    }

    /**
     * True when $code is the newest unexpired code for $phone; uses it up.
     * Wrong guesses count towards MAX_ATTEMPTS. Does not sign anyone in.
     */
    public function consume(string $phone, string $code): bool
    {
        if (self::anyCodeAccepted()) {
            // Local shortcut: use up whatever code was sent and accept any code of the right length.
            OtpCode::where('phone', $phone)->usable()->update(['consumed_at' => now()]);

            return strlen($code) === (int) config('services.otp.length', 6) && ctype_digit($code);
        }

        return DB::transaction(function () use ($phone, $code) {
            $otp = OtpCode::where('phone', $phone)->usable()->latest('id')->lockForUpdate()->first();

            if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            $otp->increment('attempts');

            if (! Hash::check($code, $otp->code_hash)) {
                return false;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    /**
     * OTP_ANY_CODE works only on a developer's machine (APP_ENV=local), never
     * in production, staging or tests, whatever the .env says.
     */
    public static function anyCodeAccepted(): bool
    {
        return config('services.otp.any_code') === true && app()->environment('local');
    }

    private function generateCode(): string
    {
        $length = (int) config('services.otp.length', 6);

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
}
