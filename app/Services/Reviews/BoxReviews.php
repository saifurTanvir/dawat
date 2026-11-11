<?php

namespace App\Services\Reviews;

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\BoxReview;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Delivery;
use App\Services\Push\Push;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F47: reviews of delivered boxes, replies, and the kitchen's star average.
 */
class BoxReviews
{
    public const DAYS = 3;

    public static function deliveredAt(Order $order): ?Carbon
    {
        // Only the real delivery time: updated_at moves when a box goes on a bill.
        return $order->status === OrderStatus::Delivered ? $order->delivered_at : null;
    }

    /** Until when this box can be reviewed (or the review changed). */
    public static function until(Order $order): ?Carbon
    {
        return self::deliveredAt($order)?->copy()->addDays(self::DAYS);
    }

    public static function canReview(Order $order, User $user): bool
    {
        return $order->status === OrderStatus::Delivered
            && $order->recipient()?->id === $user->id
            && ($until = self::until($order)) !== null && $until->isFuture();
    }

    public function submit(Order $order, User $user, int $stars, ?string $body): BoxReview
    {
        if (! self::canReview($order, $user)) {
            throw ValidationException::withMessages(['stars' => 'খাবার পৌঁছানোর '.Order::banglaDigits((string) self::DAYS).' দিনের মধ্যেই রিভিউ দেওয়া যায়।']);
        }
        if ($stars < 1 || $stars > 5) {
            throw ValidationException::withMessages(['stars' => '১ থেকে ৫ তারা দিন।']);
        }

        $review = DB::transaction(function () use ($order, $user, $stars, $body) {
            $review = BoxReview::updateOrCreate(['order_id' => $order->id], [
                'kitchen_id' => $order->kitchen_id,
                'user_id' => $user->id,
                'stars' => $stars,
                'body' => filled($body) ? trim($body) : null,
            ]);
            $this->recompute($order->kitchen);

            return $review;
        });

        if ($stars <= 2) {
            $title = "কম রেটিং ({$review->stars()}): {$order->kitchen->display_name}";
            $text = 'বক্স '.$order->code().' · '.($order->title ?? '').($review->body ? " — “{$review->body}”" : '');
            if ($order->shahidar_id) {
                Push::toUser($order->shahidar_id, $title, $text, '/shahidar/kitchens/'.$order->kitchen_id, 'review-'.$review->id);
            }
            User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser($id, $title, $text, '/admin/kitchens/'.$order->kitchen_id, 'review-'.$review->id));
        }

        return $review;
    }

    public function reply(BoxReview $review, User $by, string $role, string $text): BoxReview
    {
        $review->forceFill(['reply' => trim($text), 'reply_by' => $by->id, 'reply_role' => $role, 'replied_at' => now(), 'reply_hidden' => false])->save();
        Push::toUser($review->user_id, 'আপনার রিভিউর উত্তর এসেছে', $review->replyLabel().': '.trim($text), '/history', 'review-'.$review->id);

        return $review;
    }

    public function recompute(Kitchen $kitchen): void
    {
        $row = BoxReview::where('kitchen_id', $kitchen->id)->selectRaw('AVG(stars) AS a, COUNT(*) AS n')->first();
        $kitchen->forceFill(['rating_avg' => $row->n ? round((float) $row->a, 2) : null, 'rating_count' => (int) $row->n])->save();
    }

    /** For the cook, her shahidar and the admin. @return array<string, mixed> */
    public static function row(BoxReview $r, bool $withName = false): array
    {
        return [
            'id' => $r->id,
            'stars' => $r->stars,
            'body' => $r->body,
            'who' => $withName ? ($r->user?->name ?: $r->reviewerLabel()).' ('.$r->reviewerLabel().')' : $r->reviewerLabel(),
            'title' => $r->order?->title,
            'meal_slot_label' => $r->order?->meal_slot->label(),
            'date' => $r->order?->service_date?->toDateString(),
            'at' => $r->created_at?->toIso8601String(),
            'reply' => $r->reply,
            'reply_label' => $r->replyLabel(),
            'reply_hidden' => $r->reply_hidden,
        ];
    }

    /** How the customer paid for a box. */
    public static function paymentLabel(Order $o): ?string
    {
        if (! in_array($o->status, [OrderStatus::Delivered, OrderStatus::Failed], true)) {
            return null;
        }
        if ($o->paid_at) {
            return match ($o->payment_method) {
                'bill' => 'বিলে পরিশোধ'.($o->bill ? ' · '.$o->bill->number : ''),
                'cash_to_cook' => 'গৃহিণীকে দিয়েছেন',
                default => 'পরিশোধ হয়েছে',
            };
        }
        if ($o->bill && $o->bill->status !== 'void') {
            return 'বিলে আছে · '.$o->bill->number.' ('.Bill::STATUS_LABELS[$o->bill->status].')';
        }
        if ($o->customer?->paysByBill()) {
            return 'পরের বিলে যোগ হবে';
        }

        return 'বাকি';
    }

    public static function deliveredBy(Order $o): ?string
    {
        return match ($o->confirm_method) {
            'cook_tap' => 'গৃহিণী নিজে দিয়েছেন',
            Delivery::BY_CUSTOMER_SCAN => 'আপনি স্টিকার স্ক্যান করেছেন',
            Delivery::BY_SHAHIDAR_CODE, Delivery::BY_SHAHIDAR_TAP => 'শাহিদার '.($o->shahidar?->name ?? ''),
            default => $o->shahidar ? 'শাহিদার '.$o->shahidar->name : null,
        };
    }
}
