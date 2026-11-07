<?php

namespace App\Services\Otp;

/**
 * Delivers a one-time code to a phone. Swap the implementation per SMS gateway
 * by setting OTP_DRIVER; nothing else in the app needs to change.
 * Build the SMS text with $purpose->message($code, $context).
 */
interface OtpSender
{
    public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void;
}
