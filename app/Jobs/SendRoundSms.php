<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\RoundStop;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** F95: the cook has no app notifications turned on — she hears the area manager is coming by SMS. */
class SendRoundSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $stopId, public string $when) {}

    public function handle(OtpSender $sender): void
    {
        $stop = RoundStop::with('kitchen.user')->find($this->stopId);
        if (! $stop || $stop->picked_at) {
            return;
        }
        try {
            $sender->send($stop->kitchen->user->phone, '', OtpPurpose::RoundComing, ['when' => $this->when, 'boxes' => Order::banglaDigits($stop->boxes)]);
        } catch (OtpDeliveryFailedException) {
            $this->release(60);
        }
    }
}
