<?php

namespace App\Jobs;

use App\Models\OfficeDrop;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use App\Services\Push\Push;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** F68: tells the office's contact person the drop is coming, and its handover code. */
class SendOfficeDropCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $dropId, public string $code) {}

    public function handle(OtpSender $sender): void
    {
        $drop = OfficeDrop::with('company.contact')->find($this->dropId);
        $contact = $drop?->company?->contact;
        if (! $contact) {
            return;
        }
        $slot = $drop->meal_slot->label();

        if ($this->attempts() === 1) {
            Push::toUser($contact, "আজ {$slot}: অফিসের খাবার আসছে", "এলাকা ম্যানেজার এলে সব বক্স বুঝে নিয়ে কোড বলবেন: {$this->code}", '/', 'drop-'.$drop->id);
        }
        if (! config('dawat.orders.sms')) {
            return;
        }

        try {
            $sender->send($contact->phone, $this->code, OtpPurpose::OfficeDrop, ['slot' => $slot, 'company' => $drop->company->name]);
        } catch (OtpDeliveryFailedException) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
