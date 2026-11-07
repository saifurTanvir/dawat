<?php

namespace App\Services\Otp;

/**
 * Delivers a one-time code to a phone. Swap the implementation per SMS gateway
 * by setting OTP_DRIVER; nothing else in the app needs to change.
 */
interface OtpSender
{
    public function send(string $phone, string $code): void;
}
