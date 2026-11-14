<?php

namespace App\Services\Reviews;

use App\Enums\OrderStatus;
use App\Models\BoxReview;
use App\Models\Kitchen;
use App\Models\Order;
use App\Services\Money\CookFees;

/**
 * F47: what a cook sold, day by day and meal by meal, and what she earns
 * (Dawat's commission taken off). Numbers only — never who bought.
 */
class Sales
{
    /** @return array{days: list<array>, totals: array, rating_avg: ?float, rating_count: int, reviews: list<array>} */
    public function forKitchen(Kitchen $kitchen, int $days = 14, bool $withNames = false): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $orders = Order::where('kitchen_id', $kitchen->id)->whereDate('service_date', '>=', $from)
            ->orderByDesc('service_date')->get();

        $rows = $orders->groupBy(fn (Order $o) => $o->service_date->toDateString().'|'.$o->meal_slot->value)
            ->map(function ($group) {
                $first = $group->first();
                $live = $group->reject(fn (Order $o) => $o->status === OrderStatus::Cancelled);
                $cooked = $group->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Delivered, OrderStatus::Failed], true));
                $viaDawat = $cooked->reject(fn (Order $o) => $o->payment_method === 'cash_to_cook');
                $gross = (int) $viaDawat->sum('food_paisa');
                // F56: fees fixed on each box.
                $fee = (int) $viaDawat->sum('platform_fee_paisa');
                $cut = (int) $viaDawat->sum('cook_delivery_paisa');

                return [
                    'date' => $first->service_date->toDateString(),
                    'meal_slot' => $first->meal_slot->value,
                    'meal_slot_label' => $first->meal_slot->label(),
                    'titles' => $group->pluck('title')->filter()->unique()->values(),
                    'regular_plates' => (int) $live->reject(fn (Order $o) => $o->isOneOff())->sum('plates'),
                    'feed_plates' => (int) $live->filter(fn (Order $o) => $o->isOneOff())->sum('plates'),
                    'delivered' => $group->where('status', OrderStatus::Delivered)->count(),
                    'failed' => $group->where('status', OrderStatus::Failed)->count(),
                    'cancelled' => $group->where('status', OrderStatus::Cancelled)->count(),
                    'open' => $group->whereIn('status', [OrderStatus::Booked, OrderStatus::Asking, OrderStatus::PickedUp])->count(),
                    'gross_paisa' => $gross,
                    'platform_fee_paisa' => $fee,
                    'delivery_cut_paisa' => $cut,
                    'earned_paisa' => $gross - $fee - $cut,
                    'cash_to_cook_paisa' => (int) $cooked->where('payment_method', 'cash_to_cook')->sum('food_paisa'),
                    'settled' => $viaDawat->isNotEmpty() && $viaDawat->every(fn (Order $o) => $o->cook_settlement_id !== null),
                ];
            })->values();

        $reviews = BoxReview::where('kitchen_id', $kitchen->id)->with(['order', 'user', 'replier'])->latest('id')->limit(30)->get();

        return [
            'days' => $rows->all(),
            'totals' => [
                'earned_paisa' => (int) $rows->sum('earned_paisa'),
                'settled_paisa' => (int) $rows->where('settled', true)->sum('earned_paisa'),
                'due_paisa' => (int) $rows->where('settled', false)->sum('earned_paisa'),
                'plates' => (int) $rows->sum(fn ($r) => $r['regular_plates'] + $r['feed_plates']),
                'gross_paisa' => (int) $rows->sum('gross_paisa'),
                'platform_fee_paisa' => (int) $rows->sum('platform_fee_paisa'),
                'delivery_cut_paisa' => (int) $rows->sum('delivery_cut_paisa'),
                // F56: the rules in force now, for the "how is this worked out" line.
                'fees' => app(CookFees::class)->forApp($kitchen),
                'from' => $from,
            ],
            'rating_avg' => $kitchen->rating_avg !== null ? (float) $kitchen->rating_avg : null,
            'rating_count' => (int) $kitchen->rating_count,
            'reviews' => $reviews->map(fn (BoxReview $r) => BoxReviews::row($r, $withNames))->all(),
        ];
    }
}
