<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Push\Push;
use App\Support\DhakaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F39: customers who pay weekly or monthly get a bill for the boxes they
 * received (and the ones that came back because nobody was home). Part
 * payments leave the rest as বাকি. Bill payments go straight to Dawat, so
 * they never count as cash held by a shahidar.
 */
class Billing
{
    /** Boxes charged to the customer and not yet paid or billed. */
    public function unbilled(Customer $customer, ?string $to = null): Builder
    {
        return Order::where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Failed])
            ->whereNull('paid_at')
            ->whereNull('bill_id')
            ->where('payment_method', '!=', 'cash_to_cook')
            ->when($to, fn ($q) => $q->whereDate('service_date', '<=', $to));
    }

    public function unbilledTotal(Customer $customer): int
    {
        return (int) $this->unbilled($customer)->sum(DB::raw('food_paisa + delivery_fee_paisa'));
    }

    public function issue(Customer $customer, ?User $by = null, ?string $to = null, bool $notify = true): Bill
    {
        $to ??= BookingPlanner::today();

        $bill = DB::transaction(function () use ($customer, $by, $to) {
            $orders = $this->unbilled($customer, $to)->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['customer' => 'বিল করার মতো কোনো বক্স নেই।']);
            }
            $food = (int) $orders->sum('food_paisa');
            $delivery = (int) $orders->sum('delivery_fee_paisa');

            $bill = Bill::create([
                'number' => $this->nextNumber(),
                'customer_id' => $customer->id,
                'period_from' => $orders->min('service_date'),
                'period_to' => $orders->max('service_date'),
                'orders_count' => $orders->count(),
                'food_paisa' => $food,
                'delivery_paisa' => $delivery,
                'total_paisa' => $food + $delivery,
                'due_on' => now(DhakaTime::TZ)->addDays(3)->toDateString(),
                'token' => Str::random(40),
                'issued_by' => $by?->id,
            ]);
            Order::whereKey($orders->modelKeys())->update(['bill_id' => $bill->id]);

            return $bill;
        });

        if ($notify) {
            Push::toUser($customer->user_id, 'নতুন বিল '.$bill->number,
                Order::banglaDigits($bill->orders_count).'টা বক্স · মোট ৳'.Order::banglaDigits(intdiv($bill->total_paisa, 100)),
                '/bill/'.$bill->token, 'bill-'.$bill->id);
        }

        return $bill;
    }

    public function pay(Bill $bill, int $amountPaisa, string $method, ?User $by = null, ?string $note = null): Bill
    {
        return DB::transaction(function () use ($bill, $amountPaisa, $method, $by, $note) {
            $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if (in_array($bill->status, ['paid', 'void'], true)) {
                throw ValidationException::withMessages(['amount' => 'এই বিল আর খোলা নেই।']);
            }
            if ($amountPaisa <= 0 || $amountPaisa > $bill->duePaisa()) {
                throw ValidationException::withMessages(['amount' => 'টাকার পরিমাণ ১ থেকে বাকি টাকার মধ্যে হতে হবে।']);
            }

            BillPayment::create([
                'bill_id' => $bill->id,
                'amount_paisa' => $amountPaisa,
                'method' => array_key_exists($method, Bill::METHOD_LABELS) ? $method : 'other',
                'received_by' => $by?->id,
                'note' => $note,
            ]);

            $paid = $bill->paid_paisa + $amountPaisa;
            $full = $paid >= $bill->total_paisa;
            $bill->forceFill(['paid_paisa' => $paid, 'status' => $full ? 'paid' : 'partial', 'paid_at' => $full ? now() : null])->save();

            if ($full) {
                // Paid to Dawat directly: not shahidar cash (see Settlements::shahidarCash).
                Order::where('bill_id', $bill->id)->whereNull('paid_at')
                    ->update(['paid_at' => now(), 'collected_by' => $by?->id, 'payment_method' => 'bill']);
            }

            return $bill;
        });
    }

    /** Cancel a bill (a mistake): its boxes go back to "not billed". */
    public function void(Bill $bill): Bill
    {
        return DB::transaction(function () use ($bill) {
            $bill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->paid_paisa > 0) {
                throw ValidationException::withMessages(['bill' => 'টাকা নেওয়া হয়েছে এমন বিল বাতিল করা যায় না।']);
            }
            Order::where('bill_id', $bill->id)->update(['bill_id' => null]);
            $bill->forceFill(['status' => 'void'])->save();

            return $bill;
        });
    }

    /**
     * Run daily: weekly customers get a bill every Saturday (for up to
     * Friday), monthly customers on the 1st (for last month).
     *
     * @return Collection<int, Bill>
     */
    public function issueDue(?Carbon $today = null): Collection
    {
        $today ??= now(DhakaTime::TZ);
        $bills = collect();

        $plans = [];
        if ($today->isSaturday()) {
            $plans['weekly'] = $today->copy()->subDay()->toDateString();
        }
        if ($today->day === 1) {
            $plans['monthly'] = $today->copy()->subDay()->toDateString();
        }

        foreach ($plans as $plan => $to) {
            Customer::where('billing', $plan)->get()->each(function (Customer $c) use ($to, $bills) {
                if ($this->unbilled($c, $to)->exists()) {
                    $bills->push($this->issue($c, null, $to));
                }
            });
        }

        return $bills;
    }

    /** Everyone who owes: unbilled boxes plus what is left on open bills. @return Collection<int, array> */
    public function dues(): Collection
    {
        $open = Bill::whereIn('status', ['open', 'partial'])->with('customer.user')->get()->groupBy('customer_id');
        $unbilled = Order::whereNotNull('customer_id')
            ->whereIn('status', [OrderStatus::Delivered, OrderStatus::Failed])
            ->whereNull('paid_at')->whereNull('bill_id')->where('payment_method', '!=', 'cash_to_cook')
            ->selectRaw('customer_id, COUNT(*) AS n, SUM(food_paisa + delivery_fee_paisa) AS amount')
            ->groupBy('customer_id')->get()->keyBy('customer_id');

        $ids = $open->keys()->merge($unbilled->keys())->unique();
        $customers = Customer::with('user')->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => [
            'customer' => $customers[$id],
            'unbilled' => (int) ($unbilled[$id]->amount ?? 0),
            'unbilled_boxes' => (int) ($unbilled[$id]->n ?? 0),
            'bills' => $open->get($id, collect()),
            'billed_due' => (int) $open->get($id, collect())->sum(fn (Bill $b) => $b->duePaisa()),
        ])->map(fn ($r) => $r + ['total' => $r['unbilled'] + $r['billed_due']])
            ->sortByDesc('total')->values();
    }

    private function nextNumber(): string
    {
        $prefix = 'DW-'.now(DhakaTime::TZ)->format('ym').'-';
        $last = Bill::where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
