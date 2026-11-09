<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
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
                throw ValidationException::withMessages(['order' => 'এই গ্রাহক বিলে টাকা দেন; শাহিদার টাকা নেবেন না।']);
            }
            if ($order->paid_at === null) {
                $order->forceFill(['paid_at' => now(), 'collected_by' => $collector->id, 'payment_method' => 'cash'])->save();
            }

            return $order;
        });
    }

    /** @return Collection<int, array{kitchen: Kitchen, orders: int, gross: int, commission: int, payable: int, from: ?string, to: ?string}> */
    public function cooksDue(): Collection
    {
        // Self-pickup boxes were paid to the cook directly (F29).
        $rows = Order::whereIn('status', self::COOKED)->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')
            ->selectRaw('kitchen_id, COUNT(*) AS n, SUM(food_paisa) AS gross, MIN(service_date) AS d0, MAX(service_date) AS d1')
            ->groupBy('kitchen_id')->get();
        $kitchens = Kitchen::whereIn('id', $rows->pluck('kitchen_id'))->with('user')->get()->keyBy('id');

        return $rows->map(function ($r) use ($kitchens) {
            $gross = (int) $r->gross;
            $commission = $this->commission($gross);

            return [
                'kitchen' => $kitchens[$r->kitchen_id],
                'orders' => (int) $r->n,
                'gross' => $gross,
                'commission' => $commission,
                'payable' => $gross - $commission,
                'from' => (string) $r->d0,
                'to' => (string) $r->d1,
            ];
        })->sortByDesc('payable')->values();
    }

    /** @return Collection<int, array{shahidar: User, orders: int, cash: int}> */
    public function shahidarCash(): Collection
    {
        $rows = Order::whereNotNull('paid_at')->where('payment_method', 'cash')->whereNull('shahidar_settlement_id')
            ->selectRaw('collected_by, COUNT(*) AS n, SUM(food_paisa + delivery_fee_paisa) AS cash')
            ->groupBy('collected_by')->get();
        $users = User::whereIn('id', $rows->pluck('collected_by'))->get()->keyBy('id');

        return $rows->filter(fn ($r) => isset($users[$r->collected_by]))
            ->map(fn ($r) => ['shahidar' => $users[$r->collected_by], 'orders' => (int) $r->n, 'cash' => (int) $r->cash])
            ->sortByDesc('cash')->values();
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
        return (int) Order::whereNotNull('paid_at')->where('collected_by', $shahidar->id)
            ->whereNull('shahidar_settlement_id')->sum(DB::raw('food_paisa + delivery_fee_paisa'));
    }

    public function payCook(Kitchen $kitchen, User $by, ?string $note = null): Settlement
    {
        return DB::transaction(function () use ($kitchen, $by, $note) {
            $orders = Order::where('kitchen_id', $kitchen->id)->whereIn('status', self::COOKED)
                ->whereNull('cook_settlement_id')->where('payment_method', '!=', 'cash_to_cook')->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['kitchen' => 'এই গৃহিণীর পাওনা কিছু নেই।']);
            }
            $gross = (int) $orders->sum('food_paisa');
            $commission = $this->commission($gross);

            $settlement = Settlement::create([
                'kind' => 'cook',
                'kitchen_id' => $kitchen->id,
                'orders_count' => $orders->count(),
                'gross_paisa' => $gross,
                'commission_paisa' => $commission,
                'amount_paisa' => $gross - $commission,
                'from_date' => $orders->min('service_date'),
                'to_date' => $orders->max('service_date'),
                'note' => $note,
                'settled_by' => $by->id,
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
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['shahidar' => 'এই শাহিদারের কাছে জমা টাকা নেই।']);
            }
            $cash = (int) $orders->sum(fn (Order $o) => $o->totalPaisa());

            $settlement = Settlement::create([
                'kind' => 'shahidar',
                'shahidar_id' => $shahidar->id,
                'orders_count' => $orders->count(),
                'gross_paisa' => $cash,
                'amount_paisa' => $cash,
                'from_date' => $orders->min('service_date'),
                'to_date' => $orders->max('service_date'),
                'note' => $note,
                'settled_by' => $by->id,
            ]);
            Order::whereIn('id', $orders->pluck('id'))->update(['shahidar_settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    private function commission(int $gross): int
    {
        return (int) round($gross * (float) config('dawat.orders.commission_percent', 0) / 100);
    }
}
