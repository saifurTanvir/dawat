<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Sticker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F27: pickup and handover. A delivery is confirmed one of three ways:
 * the customer scans the box, the shahidar enters the customer's SMS code,
 * or the shahidar just taps "দিয়েছি" (marked for an admin to check).
 */
class Delivery
{
    public const BY_CUSTOMER_SCAN = 'customer_scan';

    public const BY_SHAHIDAR_CODE = 'shahidar_code';

    public const BY_SHAHIDAR_TAP = 'shahidar_tap';

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

            return $order;
        });
    }

    /** By the shahidar at the door: with the customer's code, or a plain tap. */
    public function deliverByShahidar(Order $order, User $shahidar, ?string $code): Order
    {
        return DB::transaction(function () use ($order, $shahidar, $code) {
            $order = $this->lock($order);
            $this->expect($order, OrderStatus::PickedUp, 'আগে পিকআপ চিহ্নিত করুন।');

            if (filled($code)) {
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
