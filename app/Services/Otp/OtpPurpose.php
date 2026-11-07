<?php

namespace App\Services\Otp;

/** Why a code is being sent; gateways word the SMS accordingly. */
enum OtpPurpose: string
{
    case Login = 'login';
    case ContactNumber = 'contact_number';

    /**
     * The SMS text, in Bangla.
     *
     * @param  array{kitchen?: string}  $context
     */
    public function message(string $code, array $context = []): string
    {
        return match ($this) {
            self::Login => "দাওয়াত লগইন কোড: {$code}। কাউকে এই কোড দেবেন না।",
            self::ContactNumber => sprintf(
                'দাওয়াত: "%s" আপনার নম্বরটি ক্রেতাদের যোগাযোগের নম্বর হিসেবে দিতে চান। রাজি থাকলে তাঁকে এই কোড দিন: %s',
                $context['kitchen'] ?? 'একজন রাঁধুনি',
                $code,
            ),
        };
    }
}
