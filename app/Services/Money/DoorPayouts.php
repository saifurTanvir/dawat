<?php

namespace App\Services\Money;

use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\RoundStop;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Orders\Settlements;
use App\Services\Push\Push;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F110: an area manager pays the cooks he serves at their door from the cash
 * he holds — on each cook's pay day (daily, or her weekly day) — and sees his
 * day in one card: collected, paid to cooks, to hand to Dawat. The cook
 * confirms each payout in her app; what she does not confirm, admins see.
 */
class DoorPayouts
{
    public function __construct(private readonly Settlements $money) {}

    /** "আজকের হিসাব" and the cooks he can pay now. */
    public function forShahidar(User $shahidar): array
    {
        $collected = (int) Order::whereNotNull('paid_at')->where('collected_by', $shahidar->id)->whereNull('shahidar_settlement_id')
            ->sum(DB::raw('food_paisa + delivery_fee_paisa'));
        $paid = (int) Settlement::where('kind', 'cook')->where('cash_from_shahidar_id', $shahidar->id)->whereNull('offset_settlement_id')->sum('amount_paisa');

        return [
            'collected_paisa' => $collected,
            'paid_cooks_paisa' => $paid,
            'to_dawat_paisa' => $collected - $paid,
            'cooks' => $this->kitchens($shahidar)->map(function (Kitchen $k) {
                $due = $this->money->dueFor($k);

                return [
                    'kitchen_id' => $k->id,
                    'name' => $k->display_name,
                    'due_paisa' => $due['payable'],
                    'orders' => $due['orders'],
                    'pay_label' => $k->payLabel(),
                    'pay_today' => $k->paidOn(now('Asia/Dhaka')),
                ];
            })->filter(fn (array $r) => $r['due_paisa'] > 0)->sortByDesc('pay_today')->values(),
        ];
    }

    /** "৳… হাতে দিয়েছি": everything she is owed, from his cash. */
    public function pay(Kitchen $kitchen, User $shahidar): Settlement
    {
        abort_unless($this->kitchens($shahidar)->contains('id', $kitchen->id), 404);
        if (! $kitchen->paidOn(now('Asia/Dhaka'))) {
            throw ValidationException::withMessages(['kitchen' => 'উনি টাকা নেন '.$kitchen->payLabel().' — আজ নয়।']);
        }
        $s = $this->money->payCook($kitchen, $shahidar, 'এলাকা ম্যানেজার বাসায় নগদে দিয়েছেন', $shahidar);
        Push::toUser($kitchen->user, 'এলাকা ম্যানেজার ৳'.Order::banglaDigits(number_format(intdiv($s->amount_paisa, 100))).' দিয়েছেন — পেয়েছেন?',
            'অ্যাপে "হ্যাঁ, পেয়েছি" বা "পাইনি" চাপুন।', '/cook', 'payout-'.$s->id);

        return $s;
    }

    /** Payouts at her door she has not confirmed yet (return-round ones are asked on the round card). */
    public static function forCook(Kitchen $kitchen): Collection
    {
        return Settlement::where('kind', 'cook')->where('kitchen_id', $kitchen->id)->whereNotNull('cash_from_shahidar_id')
            ->whereNull('cook_confirmed_at')->where('created_at', '>', now()->subDays(7))
            ->whereNotIn('id', RoundStop::whereNotNull('settlement_id')->select('settlement_id'))
            ->latest('id')->get()
            ->map(fn (Settlement $s) => [
                'id' => $s->id,
                'amount_paisa' => (int) $s->amount_paisa,
                'boxes' => $s->orders_count,
                'disputed' => $s->cook_disputed_at !== null,
                'when' => $s->created_at->toIso8601String(),
            ])->values();
    }

    public function confirm(Settlement $s, bool $got): void
    {
        $s->forceFill($got ? ['cook_confirmed_at' => now(), 'cook_disputed_at' => null] : ['cook_disputed_at' => now()])->save();
        RoundStop::where('settlement_id', $s->id)->update($got ? ['cook_confirmed_at' => now(), 'cook_disputed_at' => null] : ['cook_disputed_at' => now()]);
        if (! $got && $s->cash_from_shahidar_id) {
            Push::toUser($s->cash_from_shahidar_id, $s->kitchen->display_name.' বলছেন টাকা পাননি',
                'ফোন করে মিলিয়ে নিন। দাওয়াত টিমও দেখছে।', '/shahidar', 'payout-dispute-'.$s->id);
        }
    }

    /** The cooks this area manager serves: his pairings, and boxes he carried. */
    private function kitchens(User $shahidar): Collection
    {
        $ids = Pairing::open()->where('shahidar_id', $shahidar->id)->pluck('kitchen_id')
            ->merge(Order::where('shahidar_id', $shahidar->id)->whereNull('cook_settlement_id')->pluck('kitchen_id'))->unique();

        return Kitchen::whereIn('id', $ids)->with('user')->get();
    }
}
