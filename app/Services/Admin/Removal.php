<?php

namespace App\Services\Admin;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F42: undoing what an admin set up — a test customer, a cook who left, a
 * pairing that did not work out. Anything can be removed except money that
 * has already changed hands (a cook paid, cash handed to Dawat, a bill
 * payment): those records keep the accounts right, so they block a delete
 * and the admin can stop or unpair instead.
 */
class Removal
{
    public function __construct(private readonly BookingPlanner $planner) {}

    /** Boxes whose money has been settled with a cook or a shahidar. */
    private function settled(Builder $orders): int
    {
        return (clone $orders)->where(fn ($q) => $q->whereNotNull('cook_settlement_id')->orWhereNotNull('shahidar_settlement_id'))->count();
    }

    private function bn(int $n): string
    {
        return Order::banglaDigits((string) $n);
    }

    /* ---------- pairing ---------- */

    /** End the pairing without a new one: the customer waits for a new cook, upcoming boxes are cancelled. */
    public function unpair(Customer $customer, User $by, string $reason): int
    {
        return DB::transaction(function () use ($customer, $by, $reason) {
            $cancelled = $this->cancelUpcoming(Order::where('customer_id', $customer->id), $reason);
            $customer->pairing()->first()?->forceFill(['ended_at' => now(), 'end_reason' => $reason, 'ended_by' => $by->id])->save();
            $customer->forceFill(['status' => CustomerStatus::Waiting])->save();

            return $cancelled;
        });
    }

    /** A stopped customer wants food again: back to "waiting" for a pairing. */
    public function restart(Customer $customer): void
    {
        $customer->forceFill(['status' => CustomerStatus::Waiting])->save();
    }

    private function cancelUpcoming(Builder $orders, string $reason): int
    {
        $n = 0;
        foreach ((clone $orders)->whereIn('status', [OrderStatus::Booked, OrderStatus::Asking])
            ->whereDate('service_date', '>=', BookingPlanner::today())->get() as $order) {
            $this->planner->cancel($order, 'admin', $reason);
            $n++;
        }

        return $n;
    }

    /* ---------- customer ---------- */

    /** @return list<string> why it cannot be deleted */
    public function customerBlockers(Customer $customer): array
    {
        $reasons = [];
        if ($n = $this->settled(Order::where('customer_id', $customer->id))) {
            $reasons[] = "{$this->bn($n)}টা বক্সের টাকার হিসাব (গৃহিণীকে দেওয়া বা শাহিদার থেকে জমা) হয়ে গেছে।";
        }
        if (BillPayment::whereHas('bill', fn ($q) => $q->where('customer_id', $customer->id))->exists()) {
            $reasons[] = 'বিলের টাকা নেওয়া হয়েছে।';
        }

        return $reasons;
    }

    public function customerSummary(Customer $customer): string
    {
        return collect([
            $this->bn(Order::where('customer_id', $customer->id)->count()).'টা বক্স',
            $this->bn($customer->pairings()->count()).'টা জুটি',
            $this->bn($customer->bills()->count()).'টা বিল',
        ])->implode(', ').' মুছে যাবে।';
    }

    public function deleteCustomer(Customer $customer, bool $withAccount = false): void
    {
        $this->refuse($this->customerBlockers($customer), 'নিয়মিত খাবার বন্ধ করে রাখুন');

        DB::transaction(function () use ($customer, $withAccount) {
            $user = $customer->user;
            Order::where('customer_id', $customer->id)->delete();
            $customer->delete(); // pairings, pauses and bills go with it
            if ($withAccount && $user) {
                $this->deleteAccountIfFree($user);
            }
        });
    }

    /* ---------- kitchen ---------- */

    /** @return list<string> */
    public function kitchenBlockers(Kitchen $kitchen): array
    {
        $reasons = [];
        if (Settlement::where('kitchen_id', $kitchen->id)->exists() || $this->settled(Order::where('kitchen_id', $kitchen->id))) {
            $reasons[] = 'এই গৃহিণীর টাকার হিসাব (পরিশোধ) হয়ে গেছে।';
        }

        return $reasons;
    }

    public function kitchenSummary(Kitchen $kitchen): string
    {
        $open = Pairing::where('kitchen_id', $kitchen->id)->whereNull('ended_at')->count();

        return collect([
            $this->bn($kitchen->posts()->count()).'টা পোস্ট',
            $this->bn(Order::where('kitchen_id', $kitchen->id)->count()).'টা বক্স',
        ])->implode(', ').' মুছে যাবে।'
            .($open ? " {$this->bn($open)} জন নিয়মিত গ্রাহকের জুটি খুলে যাবে, তাঁরা নতুন গৃহিণীর অপেক্ষায় যাবেন।" : '');
    }

    public function deleteKitchen(Kitchen $kitchen, User $by, bool $withAccount = false): void
    {
        $this->refuse($this->kitchenBlockers($kitchen), 'রান্নাঘর "সাময়িক বন্ধ" করে রাখুন');

        DB::transaction(function () use ($kitchen, $by, $withAccount) {
            $this->releasePairings(Pairing::where('kitchen_id', $kitchen->id), $by, 'গৃহিণী সরানো হয়েছে');
            Order::where('kitchen_id', $kitchen->id)->delete();
            Pairing::where('kitchen_id', $kitchen->id)->delete();
            $user = $kitchen->user;
            $kitchen->delete(); // posts and their comments, views, reach go with it
            if ($withAccount && $user) {
                $this->deleteAccountIfFree($user);
            }
        });
    }

    /** Customers in these pairings go back to waiting; their upcoming boxes are cancelled. */
    private function releasePairings(Builder $pairings, User $by, string $reason): int
    {
        $open = (clone $pairings)->whereNull('ended_at')->with('customer')->get();
        foreach ($open as $pairing) {
            $this->unpair($pairing->customer, $by, $reason);
        }

        return $open->count();
    }

    /* ---------- shahidar ---------- */

    /** @return list<string> */
    public function shahidarBlockers(User $shahidar): array
    {
        $held = Order::whereNotNull('paid_at')->where('payment_method', 'cash')
            ->where('collected_by', $shahidar->id)->whereNull('shahidar_settlement_id')->count();

        return $held ? ["এই শাহিদারের কাছে {$this->bn($held)}টা বক্সের নগদ টাকা জমা আছে, আগে \"টাকার হিসাব\" থেকে জমা নিন।"] : [];
    }

    public function shahidarSummary(User $shahidar): string
    {
        $open = Pairing::where('shahidar_id', $shahidar->id)->whereNull('ended_at')->count();

        return $open
            ? "{$this->bn($open)} জন নিয়মিত গ্রাহক এই শাহিদারের। তাঁদের জুটি খুলে যাবে, আজ থেকে বুক করা বক্স বাতিল হবে — পরে নতুন শাহিদার দিয়ে আবার জুটি বাঁধুন।"
            : 'এই শাহিদারের কোনো চলমান গ্রাহক নেই।';
    }

    /** Stop being a shahidar; past deliveries keep their name. */
    public function removeShahidar(User $shahidar, User $by): void
    {
        $this->refuse($this->shahidarBlockers($shahidar));

        DB::transaction(function () use ($shahidar, $by) {
            $this->releasePairings(Pairing::where('shahidar_id', $shahidar->id), $by, 'শাহিদার সরানো হয়েছে');
            $shahidar->shahidarAreas()->detach();
            $shahidar->removeRole(Roles::SHAHIDAR);
        });
    }

    /* ---------- order, account ---------- */

    /** @return list<string> */
    public function orderBlockers(Order $order): array
    {
        $reasons = [];
        if ($order->cook_settlement_id || $order->shahidar_settlement_id) {
            $reasons[] = 'এই বক্সের টাকার হিসাব হয়ে গেছে।';
        }
        if ($order->bill_id && $order->bill?->status !== 'void') {
            $reasons[] = 'বক্সটা একটা বিলে আছে, আগে বিলটা বাতিল করুন।';
        }

        return $reasons;
    }

    public function deleteOrder(Order $order): void
    {
        $this->refuse($this->orderBlockers($order), '"আজ বাদ" দিন');
        $order->delete();
    }

    /** @return list<string> */
    public function accountBlockers(User $user): array
    {
        $reasons = [];
        if ($user->hasRole(Roles::ADMIN)) {
            $reasons[] = 'অ্যাডমিন অ্যাকাউন্ট এখান থেকে মোছা যাবে না।';
        }
        if ($user->kitchen) {
            $reasons = [...$reasons, ...$this->kitchenBlockers($user->kitchen)];
        }
        if ($user->customer) {
            $reasons = [...$reasons, ...$this->customerBlockers($user->customer)];
        }
        $reasons = [...$reasons, ...$this->shahidarBlockers($user)];
        if (Settlement::where('shahidar_id', $user->id)->exists()
            || $this->settled(Order::where(fn ($q) => $q->where('shahidar_id', $user->id)->orWhere('buyer_id', $user->id)))) {
            $reasons[] = 'এই ব্যক্তির সাথে টাকার হিসাব হয়ে গেছে।';
        }

        return array_values(array_unique($reasons));
    }

    /** Remove a person completely: their kitchen, regular booking, orders and role go too. */
    public function deleteAccount(User $user, User $by): void
    {
        $this->refuse($this->accountBlockers($user), 'অ্যাকাউন্ট ব্লক করে রাখুন');

        DB::transaction(function () use ($user, $by) {
            if ($user->kitchen) {
                $this->deleteKitchen($user->kitchen, $by);
            }
            if ($user->customer) {
                $this->deleteCustomer($user->customer->fresh());
            }
            if ($user->isShahidar()) {
                $this->removeShahidar($user, $by);
            }
            $this->purge($user->fresh());
        });
    }

    /** After a kitchen or customer is gone, drop the login too unless it still has another job. */
    private function deleteAccountIfFree(User $user): void
    {
        $user = $user->fresh();
        if (! $user || $user->kitchen || $user->customer || $user->isShahidar() || $user->hasRole(Roles::ADMIN)) {
            return;
        }
        if (Order::where('buyer_id', $user->id)->orWhere('shahidar_id', $user->id)->exists()
            || Pairing::where('shahidar_id', $user->id)->exists()) {
            return;
        }
        $this->purge($user);
    }

    private function purge(User $user): void
    {
        // One-off orders this person bought (none settled, checked above) and old pairings they delivered.
        Order::where('buyer_id', $user->id)->delete();
        Order::where('shahidar_id', $user->id)->whereNotNull('customer_id')->update(['shahidar_id' => null]);
        Pairing::where('shahidar_id', $user->id)->delete();
        $user->tokens()->delete();
        $user->delete();
    }

    /** @param  list<string>  $reasons */
    private function refuse(array $reasons, ?string $instead = null): void
    {
        if ($reasons) {
            throw ValidationException::withMessages(['delete' => implode(' ', $reasons)
                .' তাই মোছা যাবে না'.($instead ? "; এর বদলে {$instead}।" : '।')]);
        }
    }
}
