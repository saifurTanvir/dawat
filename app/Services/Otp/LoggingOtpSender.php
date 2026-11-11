<?php

namespace App\Services\Otp;

use App\Models\SmsLog;
use App\Models\User;
use App\Services\Push\Push;
use App\Support\Roles;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * F50: wraps the real SMS gateway. Every send is logged (never the code);
 * a login code the gateway refused alerts the admins at once.
 */
class LoggingOtpSender implements OtpSender
{
    public function __construct(private readonly OtpSender $inner) {}

    /** The real gateway. */
    public function inner(): OtpSender
    {
        return $this->inner;
    }

    public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
    {
        try {
            $this->inner->send($phone, $code, $purpose, $context);
            $this->log($phone, $purpose, 'sent');
        } catch (OtpDeliveryFailedException $e) {
            $this->log($phone, $purpose, 'failed', $e->getMessage());
            if (in_array($purpose, [OtpPurpose::Login, OtpPurpose::ContactNumber], true)) {
                self::alertAdmins("কোড যায়নি: {$phone}", 'SMS গেটওয়ে নেয়নি — '.$e->getMessage().' অ্যাডমিন থেকে হোয়াটসঅ্যাপে কোড বা লগইন লিংক দিন।', $phone);
            }
            throw $e;
        }
    }

    private function log(string $phone, OtpPurpose $purpose, string $status, ?string $error = null): void
    {
        try {
            SmsLog::create([
                'phone' => $phone,
                'user_id' => User::where('phone', $phone)->value('id'),
                'purpose' => $purpose->value,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 200) : null,
            ]);
        } catch (Throwable) {
            // Logging must never stop a code from going out.
        }
    }

    /** One alert per phone per 10 minutes, to every admin. */
    public static function alertAdmins(string $title, string $body, string $phone): void
    {
        if (! Cache::add('sms-alert:'.$phone, 1, now()->addMinutes(10))) {
            return;
        }
        User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser($id, $title, $body, '/admin/users?tableSearch='.$phone, 'sms-'.$phone));
    }
}
