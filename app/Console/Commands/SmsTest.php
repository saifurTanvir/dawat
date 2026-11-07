<?php

namespace App\Console\Commands;

use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dawat:sms-test {phone : Your own mobile number, e.g. 017XXXXXXXX}')]
#[Description('Send one test SMS through the configured OTP_DRIVER (the code in it is not usable)')]
class SmsTest extends Command
{
    public function handle(OtpSender $sender): int
    {
        $phone = PhoneNumber::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('Invalid Bangladeshi mobile number.');

            return self::FAILURE;
        }

        try {
            $sender->send($phone, (string) random_int(100000, 999999), OtpPurpose::Login);
        } catch (OtpDeliveryFailedException $e) {
            $this->error('Not sent: '.$e->getMessage().' See storage/logs for the reason.');

            return self::FAILURE;
        }

        $this->info('Sent via '.config('services.otp.driver').'. Check the phone.');

        return self::SUCCESS;
    }
}
