<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Push\Push;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F27: pickup and handover. A delivery is confirmed by the customer scanning
 * the box (signed in), the customer's SMS code (typed by the shahidar, or
 * F67 on the box's sticker page without signing in), the shahidar scanning
 * the box's own sticker at the door (F67), or a plain tap "দিয়েছি"
 * (marked for an admin to check).
 */
class Delivery
{
    public const BY_CUSTOMER_SCAN = 'customer_scan';

    /** F67: the customer typed their SMS code on the sticker page, no sign-in. */
    public const BY_CUSTOMER_CODE = 'customer_code';

    public const BY_SHAHIDAR_CODE = 'shahidar_code';

    /** F67: the shahidar scanned this box's own sticker at the door. */
    public const BY_SHAHIDAR_SCAN = 'shahidar_scan';

    public const BY_SHAHIDAR_TAP = 'shahidar_tap';

    /** Strongest first; the admin's labels. */
    public const METHODS = [
        self::BY_CUSTOMER_SCAN => 'গ্রাহক স্ক্যান',
        self::BY_CUSTOMER_CODE => 'গ্রাহক কোড',
        self::BY_SHAHIDAR_CODE => 'কোড দিয়ে',
        self::BY_SHAHIDAR_SCAN => 'শাহিদার স্ক্যান',
        self::BY_SHAHIDAR_TAP => 'যাচাই বাকি',
        'cook_tap' => 'গৃহিণী নিজে',
    ];

    /** Wrong codes on the public sticker page before it stops taking codes. */
    public const MAX_CODE_FAILURES = 5;

    public function pickup(Order $order, User $shahidar, ?string $stickerInput): Order
    {
        return DB::transaction(function () use ($order, $shahidar, $stickerInput) {
            $order = $this->lock($order);
            $this->expect($order, OrderStatus::Booked, 'এই বক্স আগেই পিকআপ বা বাতিল হয়েছে।');

            if (filled($stickerInput)) {
                $code = Sticker::normalize($stickerInput);
                $sticker = $code ? Sticker::where('code', $code)->lockForUpdate()->first() : null;
                if (! $sticker) {
                    throw ValidationException::withMessages(['sticker' => 'এই স্টিকার চেনা যাচ্ছে না। আবার স্ক্যান করুন বা নম্বরটা লিখুন।']);
                }
                if ($sticker->order_id !== null) {
                    throw ValidationException::withMessages(['sticker' => 'এই স্টিকার অন্য বক্সে লাগানো হয়েছে। নতুন একটা নিন।']);
                }
                $sticker->forceFill(['order_id' => $order->id, 'bound_at' => now(), 'bound_by' => $shahidar->id])->save();
            }

            $order->forceFill(['status' => OrderStatus::PickedUp, 'picked_at' => now()])->save();

            // F45: the customer learns the food is on its way.
            Push::toUser($order->recipient(), "আপনার {$order->meal_slot->label()}র খাবার পথে",
                'শাহিদার '.($shahidar->name ?: '').' খাবার নিয়ে আসছেন · বক্স '.$order->code().'। ডেলিভারি কোড হাতের কাছে রাখুন।',
                $order->isOneOff() ? '/orders' : '/regular', 'order-'.$order->id);

            return $order;
        });
    }

    /**
     * By the shahidar at the door: with the customer's code, by scanning the
     * box's own sticker (F67: a sticker of another box is refused, naming
     * whose it is), or a plain tap.
     */
    public function deliverByShahidar(Order $order, User $shahidar, ?string $code, ?string $stickerInput = null): Order
    {
        return DB::transaction(function () use ($order, $shahidar, $code, $stickerInput) {
            $order = $this->lock($order);
            $this->expect($order, OrderStatus::PickedUp, 'আগে পিকআপ চিহ্নিত করুন।');

            if (filled($stickerInput)) {
                $this->checkSticker($order, $stickerInput);
                $method = self::BY_SHAHIDAR_SCAN;
            } elseif (filled($code)) {
                if (! $order->delivery_code_hash || ! Hash::check(trim($code), $order->delivery_code_hash)) {
                    throw ValidationException::withMessages(['code' => 'কোড মেলেনি। গ্রাহকের SMS-এর ৪ অঙ্কের কোডটা আবার দেখুন।']);
                }
                $method = self::BY_SHAHIDAR_CODE;
            } else {
                $method = self::BY_SHAHIDAR_TAP;
            }

            return $this->markDelivered($order, $shahidar, $method);
        });
    }

    /**
     * F67: the scanned sticker must be this box's. A mix-up names the box it
     * is really on, so the shahidar can swap before handing it over.
     */
    public function checkSticker(Order $order, string $stickerInput): void
    {
        $code = Sticker::normalize($stickerInput);
        $sticker = $code ? Sticker::with('order.customer.user', 'order.buyer')->where('code', $code)->first() : null;
        if (! $sticker) {
            throw ValidationException::withMessages(['sticker' => 'এই স্টিকার চেনা যাচ্ছে না। আবার স্ক্যান করুন।']);
        }
        if ($sticker->order_id === $order->id) {
            return;
        }
        $mine = $order->recipient()->name ?: 'এই গ্রাহক';
        if (! $sticker->order) {
            $hasOwn = Sticker::where('order_id', $order->id)->exists();
            throw ValidationException::withMessages(['sticker' => $hasOwn
                ? "ভুল স্টিকার! এটা ফাঁকা স্টিকার। {$mine}-এর বক্স {$order->code()}-এর স্টিকার স্ক্যান করুন।"
                : "বক্স {$order->code()}-এ পিকআপের সময় স্টিকার লাগানো হয়নি। গ্রাহকের SMS কোড নিন।"]);
        }
        $theirs = $sticker->order->recipient()->name ?: 'অন্য গ্রাহক';
        throw ValidationException::withMessages(['sticker' => "⚠️ ভুল বক্স! এটা {$theirs}-এর বক্স {$sticker->order->code()}। {$mine}-এর বক্স {$order->code()} খুঁজে দিন।"]);
    }

    /**
     * F67: the customer types their SMS code on the box's sticker page,
     * no sign-in. Five wrong codes and the page stops taking codes.
     */
    public function receivedWithCode(Order $order, string $code): Order
    {
        $wrong = false;
        try {
            return DB::transaction(function () use ($order, $code, &$wrong) {
                $order = $this->lock($order);
                $this->expect($order, OrderStatus::PickedUp, 'এই বক্স এখনো পথে নেই, বা আগেই নিশ্চিত হয়েছে।');
                if ($order->code_failures >= self::MAX_CODE_FAILURES) {
                    throw ValidationException::withMessages(['code' => 'অনেকবার ভুল কোড দেওয়া হয়েছে। কোডটা শাহিদারকে বলুন।']);
                }
                if (! $order->delivery_code_hash || ! Hash::check(trim($code), $order->delivery_code_hash)) {
                    $wrong = true;
                    throw ValidationException::withMessages(['code' => 'কোড মেলেনি। খাবার বুক হওয়ার SMS-এর ৪ অঙ্কের কোডটা দেখুন।']);
                }

                return $this->markDelivered($order, $order->recipient(), self::BY_CUSTOMER_CODE);
            });
        } catch (ValidationException $e) {
            // Counted outside the rolled-back transaction, so guesses add up.
            if ($wrong) {
                Order::whereKey($order->id)->increment('code_failures');
            }
            throw $e;
        }
    }

    /** By the customer, after scanning the sticker on their box. */
    public function receivedByCustomer(Order $order, User $customer): Order
    {
        return DB::transaction(function () use ($order, $customer) {
            $order = $this->lock($order);
            $this->expect($order, OrderStatus::PickedUp, 'এই বক্স এখনো পথে নেই, বা আগেই নিশ্চিত হয়েছে।');

            return $this->markDelivered($order, $customer, self::BY_CUSTOMER_SCAN);
        });
    }

    public function fail(Order $order, User $shahidar, string $reason): Order
    {
        return DB::transaction(function () use ($order, $shahidar, $reason) {
            $order = $this->lock($order);
            $this->expect($order, OrderStatus::PickedUp, 'শুধু পিকআপ হওয়া বক্সই "পৌঁছানো যায়নি" হতে পারে।');
            $order->forceFill(['status' => OrderStatus::Failed, 'failure_reason' => $reason, 'confirmed_by' => $shahidar->id])->save();
            Push::toUser($order->recipient(), 'খাবার পৌঁছানো যায়নি', "বক্স {$order->code()}: {$reason}। দরকার হলে শাহিদারকে ফোন করুন।",
                $order->isOneOff() ? '/orders' : '/regular', 'order-'.$order->id);

            // F29: two no-shows in 30 days pause "নিতে চাই" for that buyer.
            if ($order->isOneOff()) {
                $misses = Order::where('buyer_id', $order->buyer_id)->where('status', OrderStatus::Failed)
                    ->where('created_at', '>=', now()->subDays(30))->count();
                if ($misses >= 2) {
                    $order->buyer->forceFill(['order_blocked_until' => now()->addDays(30)])->save();
                }
            }

            return $order;
        });
    }

    private function markDelivered(Order $order, User $by, string $method): Order
    {
        $order->forceFill([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
            'confirm_method' => $method,
            'confirmed_by' => $by->id,
        ])->save();

        // F45: unless the customer confirmed it themselves by scanning.
        if ($method !== self::BY_CUSTOMER_SCAN) {
            Push::toUser($order->recipient(), 'খাবার কেমন লাগল?', 'বক্স '.$order->code().' পৌঁছেছে। ৩ দিনের মধ্যে তারা দিয়ে জানান।', '/history', 'order-'.$order->id);
        }

        return $order;
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function expect(Order $order, OrderStatus $status, string $message): void
    {
        if ($order->status !== $status) {
            throw ValidationException::withMessages(['order' => $message]);
        }
    }
}
