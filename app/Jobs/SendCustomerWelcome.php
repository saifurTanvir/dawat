<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** QA report: a customer the team added in admin got no word from Dawat until the first box. */
class SendCustomerWelcome implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $customerId) {}

    public function handle(OtpSender $sender): void
    {
        $customer = Customer::with('user')->find($this->customerId);
        if (! $customer?->user?->phone) {
            return;
        }
        try {
            $sender->send($customer->user->phone, '', OtpPurpose::CustomerWelcome, ['name' => trim((string) $customer->user->name)]);
        } catch (OtpDeliveryFailedException) {
            $this->release(60);
        }
    }
}
