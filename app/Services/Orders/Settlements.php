<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use App\Support\BnDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F28: cash on delivery. Shahidars collect, hand the cash to Dawat; Dawat pays
 * each cook for every box she cooked (delivered, or cooked but not received),
 * less the commission. payment_method on orders leaves room for bKash later.
 */
class Settlements
{
    /** Boxes a cook gets paid for: cooked and handed to the shahidar. */
    private const COOKED = [OrderStatus::Delivered, OrderStatus::Failed];

    public function markPaid(Order $order, User $collector): Order
    {
        return DB::transaction(function () use ($order, $collector) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== OrderStatus::Delivered) {
                throw ValidationException::withMessages(['order' => 'আগে খাবার পৌঁছানো চিহ্নিত করুন।']);
            }
            if ($order->customer?->paysByBill()) {
                throw ValidationException::withMessages(['order' => 'এই গ্রাহক বিলে টাকা দেন; এলাকা ম্যানেজার টাকা নেবেন না।']);
            }
            if ($order->paid_at === null) {
                $order->forceFill(['paid_at' => now(), 'collected_by' => $collector->id, 'payment_method' => 'cash'])->save();
            }

            return $order;
        });
    }

    /**
     * F56: Dawat's fee and the delivery share are fixed on each box (CookFees).
     *
     * @return Collection<int, array{kitchen: Kitchen, orders: int, gross: int, commission: int, delivery: int, payable: int, from: ?string, to: ?string}>
     */
    public function cooksDue(): Collection
    {
        // Self-pickup boxes were paid to the cook directly (F29).
        $rows = Order::whereIn('status', self::COOKED)->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')
            ->selectRaw('kitchen_id, COUNT(*) AS n, SUM(food_paisa) AS gross, SUM(platform_fee_paisa) AS fee, SUM(cook_delivery_paisa) AS cut, MIN(service_date) AS d0, MAX(service_date) AS d1')
            ->groupBy('kitchen_id')->get();
        $kitchens = Kitchen::whereIn('id', $rows->pluck('kitchen_id'))->with('user')->get()->keyBy('id');

        return $rows->map(function ($r) use ($kitchens) {
            $gross = (int) $r->gross;
            $commission = (int) $r->fee;
            $delivery = (int) $r->cut;

            return [
                'kitchen' => $kitchens[$r->kitchen_id],
                'orders' => (int) $r->n,
                'gross' => $gross,
                'commission' => $commission,
                'delivery' => $delivery,
                'payable' => $gross - $commission - $delivery,
                'from' => (string) $r->d0,
                'to' => (string) $r->d1,
            ];
        })->sortByDesc('payable')->values();
    }

    /**
     * Cash each area manager holds for Dawat: what he collected, less what he
     * paid cooks at their door from it (F110). Can be below zero: Dawat owes him.
     *
     * @return Collection<int, array{shahidar: User, orders: int, collected: int, paid_cooks: int, cash: int}>
     */
    public function shahidarCash(): Collection
    {
        $rows = Order::whereNotNull('paid_at')->where('payment_method', 'cash')->whereNull('shahidar_settlement_id')
            ->selectRaw('collected_by, COUNT(*) AS n, SUM(food_paisa + delivery_fee_paisa) AS cash')
            ->groupBy('collected_by')->get()->keyBy('collected_by');
        $paid = $this->cookPayoutsQuery()->selectRaw('cash_from_shahidar_id, SUM(amount_paisa) AS paid')
            ->groupBy('cash_from_shahidar_id')->pluck('paid', 'cash_from_shahidar_id');
        $ids = $rows->keys()->merge($paid->keys())->unique();
        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->filter(fn ($id) => isset($users[$id]))
            ->map(fn ($id) => [
                'shahidar' => $users[$id],
                'orders' => (int) ($rows[$id]->n ?? 0),
                'collected' => (int) ($rows[$id]->cash ?? 0),
                'paid_cooks' => (int) ($paid[$id] ?? 0),
                'cash' => (int) ($rows[$id]->cash ?? 0) - (int) ($paid[$id] ?? 0),
            ])
            ->sortByDesc('cash')->values();
    }

    /** F110: cooks paid at the door from an area manager's cash, not yet set against his hand-over. */
    private function cookPayoutsQuery()
    {
        return Settlement::where('kind', 'cook')->whereNotNull('cash_from_shahidar_id')->whereNull('offset_settlement_id');
    }

    /** F110: what this cook is owed now (the same sum "পরিশোধ" would pay). */
    public function dueFor(Kitchen $kitchen): array
    {
        $orders = Order::where('kitchen_id', $kitchen->id)->whereIn('status', self::COOKED)
            ->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')->get();

        return [
            'orders' => $orders->count(),
            'payable' => (int) $orders->sum('food_paisa') - (int) $orders->sum('platform_fee_paisa') - (int) $orders->sum('cook_delivery_paisa'),
            'from' => $orders->min('service_date')?->toDateString(),
            'to' => $orders->max('service_date')?->toDateString(),
        ];
    }

    /** Customers who got food (or were out) and have not paid yet. */
    public function customersOwing(): Collection
    {
        return Order::where(fn ($q) => $q->where('status', OrderStatus::Failed)
            ->orWhere(fn ($q) => $q->where('status', OrderStatus::Delivered)->whereNull('paid_at')))
            ->whereNull('paid_at')
            // F39: boxes on a bill are followed up on the bill.
            ->whereNull('bill_id')
            ->where(fn ($q) => $q->whereNull('customer_id')->orWhereHas('customer', fn ($c) => $c->where('billing', 'each')))
            ->where('payment_method', '!=', 'cash_to_cook')
            ->with(['customer.user', 'buyer', 'shahidar'])
            ->orderBy('service_date')
            ->get();
    }

    public function cashHeldBy(User $shahidar): int
    {
        $collected = (int) Order::whereNotNull('paid_at')->where('collected_by', $shahidar->id)
            ->whereNull('shahidar_settlement_id')->sum(DB::raw('food_paisa + delivery_fee_paisa'));

        return $collected - (int) $this->cookPayoutsQuery()->where('cash_from_shahidar_id', $shahidar->id)->sum('amount_paisa');
    }

    /** @param  User|null  $fromCashOf  F110: an area manager paid her at the door from the cash he holds */
    public function payCook(Kitchen $kitchen, User $by, ?string $note = null, ?User $fromCashOf = null): Settlement
    {
        return DB::transaction(function () use ($kitchen, $by, $note, $fromCashOf) {
            $orders = Order::where('kitchen_id', $kitchen->id)->whereIn('status', self::COOKED)
                ->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['kitchen' => 'এই গৃহিণীর পাওনা কিছু নেই।']);
            }
            $gross = (int) $orders->sum('food_paisa');
            $commission = (int) $orders->sum('platform_fee_paisa');
            $delivery = (int) $orders->sum('cook_delivery_paisa');

            $settlement = Settlement::create([
                'kind' => 'cook',
                'kitchen_id' => $kitchen->id,
                'orders_count' => $orders->count(),
                'gross_paisa' => $gross,
                'commission_paisa' => $commission,
                'delivery_paisa' => $delivery,
                'amount_paisa' => $gross - $commission - $delivery,
                'from_date' => $orders->min('service_date'),
                'to_date' => $orders->max('service_date'),
                'note' => $note,
                'settled_by' => $by->id,
                'cash_from_shahidar_id' => $fromCashOf?->id,
            ]);
            Order::whereIn('id', $orders->pluck('id'))->update(['cook_settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    public function receiveFromShahidar(User $shahidar, User $by, ?string $note = null): Settlement
    {
        return DB::transaction(function () use ($shahidar, $by, $note) {
            $orders = Order::whereNotNull('paid_at')->where('collected_by', $shahidar->id)
                ->whereNull('shahidar_settlement_id')->lockForUpdate()->get();
            // F110: what he paid cooks from this cash comes off what he hands over.
            $payouts = $this->cookPayoutsQuery()->where('cash_from_shahidar_id', $shahidar->id)->lockForUpdate()->get();
            if ($orders->isEmpty() && $payouts->isEmpty()) {
                throw ValidationException::withMessages(['shahidar' => 'এই এলাকা ম্যানেজারের কাছে জমা টাকা নেই।']);
            }
            $cash = (int) $orders->sum(fn (Order $o) => $o->totalPaisa());
            $paid = (int) $payouts->sum('amount_paisa');
            $net = $cash - $paid;
            if ($net < 0) {
                $note = trim(($note ? $note.' · ' : '').'দাওয়াত এলাকা ম্যানেজারকে '.BnDate::taka(-$net).' ফেরত দেবে');
            }

            $settlement = Settlement::create([
                'kind' => 'shahidar',
                'shahidar_id' => $shahidar->id,
                'orders_count' => $orders->count(),
                'gross_paisa' => $cash,
                'cook_paid_paisa' => $paid,
                'amount_paisa' => max(0, $net),
                'from_date' => $orders->min('service_date') ?? $payouts->min('from_date'),
                'to_date' => $orders->max('service_date') ?? $payouts->max('to_date'),
                'note' => $note ? mb_substr($note, 0, 200) : null,
                'settled_by' => $by->id,
            ]);
            Order::whereIn('id', $orders->pluck('id'))->update(['shahidar_settlement_id' => $settlement->id]);
            Settlement::whereIn('id', $payouts->pluck('id'))->update(['offset_settlement_id' => $settlement->id]);

            return $settlement;
        });
    }
}
