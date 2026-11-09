<?php

namespace App\Services\Push;

use App\Models\Order;
use App\Services\Otp\OtpPurpose;

/**
 * F37: the free phone notification that goes with each order SMS, plus one
 * to the shahidar who has to act on it. Sent once, before the SMS.
 */
class OrderPushes
{
    public static function send(Order $order, OtpPurpose $purpose, string $code, string $to): void
    {
        $o = $order;
        $slot = $o->meal_slot->label();
        $title = $o->title ?? '';
        $box = $o->code();
        $plates = Order::banglaDigits($o->plates);
        $recipient = $o->recipient();
        $mine = $o->isOneOff() ? '/orders' : '/regular';
        $first = trim(explode(' ', (string) $recipient->name)[0] ?? '') ?: 'গ্রাহক';

        if ($to === 'cook') {
            if ($purpose === OtpPurpose::CookNewOrder) {
                Push::toUser($o->kitchen->user_id, "নতুন অর্ডার: {$plates} প্লেট", "{$title} · বক্স {$box}। ".($o->isSelfPickup() ? 'ক্রেতা নিজে নেবেন।' : 'শাহিদার নিতে আসবেন।'), '/cook', "order-{$o->id}");
            }

            return;
        }

        match ($purpose) {
            OtpPurpose::OrderBooked => Push::toUser($recipient, "আজ {$slot}: বক্স বুক হয়েছে", "{$title} · বক্স {$box}".($code !== '' ? " · ডেলিভারি কোড {$code}" : ''), $mine, "order-{$o->id}"),
            OtpPurpose::OneOffSelf => Push::toUser($recipient, 'অর্ডার হয়েছে', "{$title} · বক্স {$box}। রান্নাঘর থেকে নিজে নেবেন।", $mine, "order-{$o->id}"),
            OtpPurpose::PriceAsk => Push::toUser($recipient, 'আজকের দাম আপনার সীমার বেশি', "{$title}। নেবেন কি না অ্যাপে জানান।", $mine, "order-{$o->id}"),
            OtpPurpose::OrderDeclined => Push::toUser($recipient, 'অর্ডারটি দেওয়া যাচ্ছে না', "{$title} · বক্স {$box}। দুঃখিত, রান্নাঘর আজ দিতে পারছে না।", $mine, "order-{$o->id}"),
            OtpPurpose::CookOff => Push::toUser($recipient, "আজ {$slot}-এ রান্না নেই", "{$o->kitchen->display_name} আজ রাঁধছেন না।", $mine, "order-{$o->id}"),
            default => null,
        };

        // The shahidar acts on new boxes and on price questions.
        if ($o->shahidar_id && ! $o->isSelfPickup()) {
            match ($purpose) {
                OtpPurpose::OrderBooked => Push::toUser($o->shahidar_id, "নতুন বক্স {$box} · {$slot}", "{$o->kitchen->display_name} থেকে {$first}-এর জন্য {$plates} প্লেট।", '/shahidar', "order-{$o->id}"),
                OtpPurpose::PriceAsk => Push::toUser($o->shahidar_id, "দাম বেশি: {$first}-কে জিজ্ঞেস করুন", "{$title} · বক্স {$box}। উত্তর না এলে প্লেট ফিডে চলে যাবে।", '/shahidar', "order-{$o->id}"),
                default => null,
            };
        }
    }
}
