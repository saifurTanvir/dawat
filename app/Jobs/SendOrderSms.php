<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Otp\OtpDeliveryFailedException;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use App\Services\Push\OrderPushes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * F26: tells a regular customer about today's box (or that there is none).
 * F37: also sends free phone notifications; the SMS goes only when ORDER_SMS_ENABLED.
 */
class SendOrderSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    /** @param  string  $to  recipient (customer/buyer) or cook */
    public function __construct(public int $orderId, public OtpPurpose $purpose, public string $code = '', public string $to = 'recipient') {}

    public function handle(OtpSender $sender): void
    {
        $order = Order::with(['customer.user', 'buyer', 'kitchen.user'])->find($this->orderId);
        if (! $order) {
            return;
        }

        // F37: the free phone notification goes first, once (not on SMS retries).
        if ($this->attempts() === 1) {
            OrderPushes::send($order, $this->purpose, $this->code, $this->to);
        }

        if (! config('dawat.orders.sms')) {
            return;
        }

        try {
            $phone = $this->to === 'cook' ? $order->kitchen->user->phone : $order->recipient()->phone;
            $sender->send($phone, $this->code, $this->purpose, [
                'slot' => $order->meal_slot->label(),
                'title' => $order->title ?? '',
                'plates' => Order::banglaDigits($order->plates),
                'total' => Order::banglaDigits(intdiv($order->totalPaisa(), 100)),
                'box' => $order->code(),
                'kitchen' => $order->kitchen->display_name,
                'price' => Order::banglaDigits(intdiv($order->food_paisa, max(1, $order->plates) * 100)),
                'limit' => Order::banglaDigits(intdiv((int) $order->customer?->max_price_paisa, 100)),
                'receive' => $order->isSelfPickup() ? 'ক্রেতা নিজে নেবেন, তাঁকে ফোন করে জায়গা বলুন।' : 'শাহিদার নিতে আসবেন।',
                'minutes' => Order::banglaDigits((int) config('dawat.orders.ask_minutes', 45)),
            ]);
        } catch (OtpDeliveryFailedException $e) {
            // Logged by the sender; retry a couple of times, then give up quietly.
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
