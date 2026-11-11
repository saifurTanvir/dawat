<?php

namespace App\Services\Otp;

/** Why a code is being sent; gateways word the SMS accordingly. */
enum OtpPurpose: string
{
    case Login = 'login';
    case ContactNumber = 'contact_number';
    /** F26: today's box is booked; $code is the 4-digit delivery code. */
    case OrderBooked = 'order_booked';
    /** F30: today's price is above the customer's limit; asks yes/no. */
    case PriceAsk = 'price_ask';
    /** F29: a feed buyer booked plates to collect from the cook. */
    case OneOffSelf = 'oneoff_self';
    /** F29: tells the cook about a new one-off order. */
    case CookNewOrder = 'cook_new_order';
    /** F29: the cook could not give a one-off order. */
    case OrderDeclined = 'order_declined';
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
            self::Login => ($context['retry'] ?? false)
                ? "আপনার Dawat কোড {$code} — dawat.cloud। কাউকে বলবেন না।"
                : "দাওয়াত লগইন কোড: {$code}। কাউকে এই কোড দেবেন না।",
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
            self::PriceAsk => sprintf(
                'দাওয়াত: আজ %s — %s, প্লেট ৳%s (আপনার সীমা ৳%s)। নিতে চাইলে অ্যাপে "নেব" চাপুন বা শাহিদারকে বলুন, %s মিনিটের মধ্যে। না জানালে আজ আসবে না।',
                $context['slot'] ?? '',
                $context['title'] ?? '',
                $context['price'] ?? '',
                $context['limit'] ?? '',
                $context['minutes'] ?? '৪৫',
            ),
            self::OneOffSelf => sprintf(
                'দাওয়াত: %s-এর %s, %s প্লেট, ৳%s বুক হয়েছে। বক্স %s। গৃহিণী ফোন করে জানাবেন কোথায় নেবেন।',
                $context['kitchen'] ?? '',
                $context['title'] ?? '',
                $context['plates'] ?? '১',
                $context['total'] ?? '',
                $context['box'] ?? '',
            ),
            self::CookNewOrder => sprintf(
                'দাওয়াত: নতুন অর্ডার — %s, %s প্লেট, বক্স %s। %s দিতে না পারলে ১০ মিনিটের মধ্যে অ্যাপে জানান।',
                $context['title'] ?? '',
                $context['plates'] ?? '১',
                $context['box'] ?? '',
                $context['receive'] ?? '',
            ),
            self::OrderDeclined => sprintf(
                'দাওয়াত: দুঃখিত, %s আজ বক্স %s দিতে পারছেন না। অর্ডারটা বাতিল হয়েছে।',
                $context['kitchen'] ?? 'গৃহিণী',
                $context['box'] ?? '',
            ),
            self::CookOff => sprintf(
                'দাওয়াত: আজ %s %s-এর রান্না নেই। আপনার শাহিদার যোগাযোগ করবেন।',
                $context['slot'] ?? '',
                $context['kitchen'] ?? 'আপনার গৃহিণীর',
            ),
        };
    }
}
