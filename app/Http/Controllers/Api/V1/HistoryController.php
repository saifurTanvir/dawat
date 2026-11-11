<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Reviews\BoxReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F47: "যা পেয়েছি" — every box a customer or buyer got, its money, and reviews. */
class HistoryController extends Controller
{
    /** GET /api/v1/my/history */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = Order::where(fn ($q) => $q->where('buyer_id', $user->id)
            ->orWhereHas('customer', fn ($c) => $c->where('user_id', $user->id)))
            ->whereDate('service_date', '>=', now()->subDays(60)->toDateString())
            ->with(['kitchen', 'shahidar', 'bill', 'customer', 'review.replier', 'buyer'])
            ->orderByDesc('service_date')->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => $this->row($o, $user))->values(),
            'meta' => ['has_more' => $orders->hasMorePages(), 'page' => $orders->currentPage()],
        ]);
    }

    /** POST /api/v1/my/history/{order}/review */
    public function review(Request $request, Order $order, BoxReviews $reviews): JsonResponse
    {
        abort_unless($order->recipient()?->id === $request->user()->id, 404);
        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:500'],
        ], ['stars.required' => 'কয় তারা দেবেন বাছুন।']);

        $reviews->submit($order, $request->user(), (int) $data['stars'], $data['body'] ?? null);

        return response()->json([
            'data' => $this->row($order->fresh(['kitchen', 'shahidar', 'bill', 'customer', 'review.replier', 'buyer']), $request->user()),
            'message' => 'ধন্যবাদ! আপনার রিভিউ গৃহিণী দেখতে পাবেন।',
        ]);
    }

    private function row(Order $o, $user): array
    {
        $review = $o->review;

        return [
            'id' => $o->id,
            'code' => $o->code(),
            'date' => $o->service_date->toDateString(),
            'meal_slot_label' => $o->meal_slot->label(),
            'title' => $o->title,
            'kitchen' => $o->kitchen->display_name,
            'plates' => $o->plates,
            'total_paisa' => $o->totalPaisa(),
            'kind' => $o->isOneOff() ? 'ফিড থেকে' : 'নিয়মিত',
            'status' => $o->status->value,
            'status_label' => $o->cancelled_by === 'cook' ? 'আজ রান্না নেই' : $o->status->label(),
            'reason' => $o->status === OrderStatus::Cancelled ? $o->cancel_reason : ($o->status === OrderStatus::Failed ? $o->failure_reason : null),
            'delivered_at' => $o->delivered_at?->toIso8601String(),
            'delivered_by' => $o->status === OrderStatus::Delivered ? BoxReviews::deliveredBy($o) : null,
            'payment' => BoxReviews::paymentLabel($o),
            'can_review' => BoxReviews::canReview($o, $user),
            'review_until' => BoxReviews::until($o)?->toIso8601String(),
            'review' => $review ? [
                'stars' => $review->stars,
                'body' => $review->body,
                'reply' => $review->reply_hidden ? null : $review->reply,
                'reply_label' => $review->reply_hidden ? null : $review->replyLabel(),
            ] : null,
        ];
    }
}
