<?php

namespace App\Services\Otp;

use RuntimeException;

class OtpThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('OTP requested too often.');
    }
}
