<?php

namespace App\Services\Kitchens;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Jobs\SendOrderSms;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Otp\OtpPurpose;
use App\Services\Push\Push;
use App\Support\Seo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * F46: what a cook does during the day, in one place, so the result is the
 * same whether she does it herself or the admin or a shahidar does it for her.
 */
class CookActions
{
    public const DECLINE_MINUTES = 10;

    public function __construct(private readonly BookingPlanner $planner) {}

    /** F38: "food is ready" for one meal; tells the shahidars who collect. Returns the number of boxes. */
    public function ready(Kitchen $kitchen, MealSlot $slot, ?string $note): int
    {
        $note = filled($note) ? trim($note) : null;
        $orders = Order::where('kitchen_id', $kitchen->id)
            ->whereDate('service_date', BookingPlanner::today())
            ->where('meal_slot', $slot)
            ->where('status', OrderStatus::Booked)
            ->where(fn ($q) => $q->whereNull('receive')->orWhere('receive', '!=', 'self'))
            ->whereNotNull('shahidar_id')
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(['meal_slot' => 'এই বেলায় শাহিদারের তোলার মতো কোনো বক্স নেই।']);
        }

        Order::whereKey($orders->modelKeys())->update(['ready_at' => now(), 'ready_note' => $note]);

        foreach ($orders->groupBy('shahidar_id') as $shahidarId => $boxes) {
            Push::toUser((int) $shahidarId,
                "{$kitchen->display_name}: ".Seo::of($slot->label()).' খাবার রেডি',
                Order::banglaDigits($boxes->count()).'টা বক্স তুলতে হবে।'.($note ? " {$note}" : ''),
                '/shahidar',
                "ready-{$kitchen->id}-{$slot->value}",
            );
        }

        return $orders->count();
    }

    /** "আজ রান্না নেই" for one meal. @return Collection<int, Order> */
    public function off(Kitchen $kitchen, MealSlot $slot): Collection
    {
        return $this->planner->cookOff($kitchen, $slot);
    }

    /** How many plates are left on the feed; regular customers' plates are not for sale. */
    public function setPlates(Post $post, int $left): Post
    {
        $booked = (int) $post->orders()->open()->sum('plates');
        $max = $post->plates_total - $booked;
        if ($left < 0 || $left > $max) {
            throw ValidationException::withMessages(['plates_left' => $booked
                ? 'নিয়মিত গ্রাহকদের '.Order::banglaDigits($booked).' প্লেট বাদে সর্বোচ্চ '.Order::banglaDigits($max).' প্লেট বাকি রাখা যাবে।'
                : 'মোট '.Order::banglaDigits($max).' প্লেটের বেশি হতে পারবে না।']);
        }

        $post->plates_left = $left;
        $post->status = $left === 0 ? PostStatus::SoldOut : PostStatus::Live;
        $post->save();

        return $post;
    }

    public function soldOut(Post $post): Post
    {
        $post->update(['plates_left' => 0, 'status' => PostStatus::SoldOut]);

        return $post;
    }

    /**
     * Fix the dish name or note; the price only while nobody has a box from
     * this post (a booked customer's price must not change under them).
     */
    public function editPost(Post $post, ?string $title, ?string $note, ?int $pricePaisa = null): Post
    {
        if ($pricePaisa !== null && $pricePaisa !== $post->price_paisa) {
            if ($post->orders()->where('status', '!=', OrderStatus::Cancelled)->exists()) {
                throw ValidationException::withMessages(['price_taka' => 'এই পোস্ট থেকে বক্স বুক হয়ে গেছে, তাই দাম বদলানো যাবে না।']);
            }
            $post->price_paisa = $pricePaisa;
        }
        if (filled($title)) {
            $post->title = trim($title);
            $post->orders()->where('status', '!=', OrderStatus::Cancelled)->update(['title' => $post->title]);
        }
        $post->note = filled($note) ? trim($note) : null;
        $post->save();

        return $post;
    }

    /** F29: the cook can't give this one-off order. Her own app allows it in the first minutes only. */
    public function decline(Order $order, bool $timeLimit = true): void
    {
        abort_unless($order->isOneOff(), 404);
        if ($timeLimit && $order->created_at->lte(now()->subMinutes(self::DECLINE_MINUTES))) {
            throw ValidationException::withMessages(['order' => '১০ মিনিট পেরিয়ে গেছে। দিতে না পারলে দাওয়াত টিম বা শাহিদারকে জানান।']);
        }

        $this->planner->cancel($order, 'cook', 'গৃহিণী দিতে পারছেন না');
        // F37: always queued: a free push goes out; the SMS only when ORDER_SMS_ENABLED.
        SendOrderSms::dispatch($order->id, OtpPurpose::OrderDeclined);
    }

    /** F29: the buyer collected it at the kitchen and paid the cook. */
    public function handed(Order $order, User $by): void
    {
        abort_unless($order->isSelfPickup(), 404);
        if ($order->status !== OrderStatus::Booked) {
            throw ValidationException::withMessages(['order' => 'এই অর্ডার আগেই শেষ বা বাতিল হয়েছে।']);
        }

        $order->forceFill([
            'status' => OrderStatus::Delivered, 'picked_at' => now(), 'delivered_at' => now(),
            'confirm_method' => 'cook_tap', 'confirmed_by' => $by->id,
            'paid_at' => now(), 'collected_by' => $by->id,
        ])->save();
        // F47
        Push::toUser($order->recipient(), 'খাবার কেমন লাগল?', 'বক্স '.$order->code().' নিয়েছেন। ৩ দিনের মধ্যে তারা দিয়ে জানান।', '/history', 'order-'.$order->id);
    }
}
