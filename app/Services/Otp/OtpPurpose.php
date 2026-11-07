<?php

namespace App\Services\Otp;

/** Why a code is being sent; gateways word the SMS accordingly. */
enum OtpPurpose: string
{
    case Login = 'login';
    case ContactNumber = 'contact_number';
    /** F26: today's box is booked; $code is the 4-digit delivery code. */
    case OrderBooked = 'order_booked';
    /** F26: the paired cook is not cooking this meal today. */
    case CookOff = 'cook_off';

    /**
     * The SMS text, in Bangla.
     *
     * @param  array{kitchen?: string, slot?: string, title?: string, plates?: string, total?: string, box?: string}  $context
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
            self::OrderBooked => sprintf(
                'দাওয়াত: আজ %s — %s, %s প্লেট, ৳%s। বক্স %s। খাবার হাতে পেলে শাহিদারকে এই কোড বলবেন: %s',
                $context['slot'] ?? '',
                $context['title'] ?? '',
                $context['plates'] ?? '১',
                $context['total'] ?? '',
                $context['box'] ?? '',
                $code,
            ),
            self::CookOff => sprintf(
                'দাওয়াত: আজ %s %s-এর রান্না নেই। আপনার শাহিদার যোগাযোগ করবেন।',
                $context['slot'] ?? '',
                $context['kitchen'] ?? 'আপনার গৃহিণীর',
            ),
        };
    }
}
