<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/** Development driver: writes the SMS to the log instead of sending it. */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
    {
        Log::info("[OTP] {$phone}: {$code} | ".$purpose->message($code, $context));
    }
}
