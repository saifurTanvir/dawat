<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/** Development driver: writes the code to the log instead of sending an SMS. */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        Log::info("[OTP] {$phone}: {$code}");
    }
}
