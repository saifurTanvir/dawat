<?php

namespace App\Actions;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Pairing;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F25: ties a customer to one cook and one shahidar. Changing the cook (food not
 * liked) or the shahidar ends the old pairing with a reason and opens a new one.
 *
 * F55: one cook, one regular customer — a cook already paired with someone else
 * cannot be chosen. A shahidar can serve any number. What the platform suggested
 * is kept on the pairing ($suggestion: kitchen_id, distance_m, shahidar_id).
 */
class PairCustomer
{
    public function __invoke(Customer $customer, Kitchen $kitchen, User $shahidar, User $by, ?string $reason = null, array $suggestion = []): Pairing
    {
        $pairing = $this->pair($customer, $kitchen, $shahidar, $by, $reason, $suggestion);

        // F45: if the cook has already posted today, the customer gets a box from it now.
        app(BookingPlanner::class)->bookLate($customer);

        return $pairing;
    }

    private function pair(Customer $customer, Kitchen $kitchen, User $shahidar, User $by, ?string $reason, array $suggestion): Pairing
    {
        return DB::transaction(function () use ($customer, $kitchen, $shahidar, $by, $reason, $suggestion) {
            // Lock the cook so two admins cannot fill the last slot at once.
            $kitchen = Kitchen::whereKey($kitchen->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $current = $customer->pairing()->first();

            if (! $kitchen->isApproved()) {
                throw ValidationException::withMessages(['kitchen_id' => 'এই রান্নাঘর এখন অনুমোদিত নয়।']);
            }
            if (! $shahidar->isShahidar() || ! $shahidar->shahidarAreas()->whereKey($customer->area_id)->exists()) {
                throw ValidationException::withMessages(['shahidar_id' => 'এই শাহিদার গ্রাহকের এলাকায় কাজ করেন না।']);
            }
            $sameCook = $current && $current->kitchen_id === $kitchen->id;
            // F55: one cook, one customer. The kitchen row is locked above, so two admins cannot both take her.
            $taken = $sameCook ? null : $kitchen->pairings()->with('customer.user')->first();
            if ($taken) {
                $who = $taken->customer?->user?->name ?: 'আরেকজন গ্রাহক';
                throw ValidationException::withMessages(['kitchen_id' => "এই গৃহিণী আগে থেকেই {$who}-এর সাথে জোড়া। একজন গৃহিণী একজন গ্রাহকের সাথেই জোড়া হতে পারেন।"]);
            }
            if ($current && $sameCook && $current->shahidar_id === $shahidar->id) {
                return $current;
            }
            if ($current && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'বদলানোর কারণ লিখুন।']);
            }

            $current?->forceFill(['ended_at' => now(), 'end_reason' => $reason, 'ended_by' => $by->id])->save();

            $pairing = Pairing::create([
                'customer_id' => $customer->id,
                'kitchen_id' => $kitchen->id,
                'shahidar_id' => $shahidar->id,
                'started_at' => now(),
                'created_by' => $by->id,
                'suggested_kitchen_id' => $suggestion['kitchen_id'] ?? null,
                'suggested_distance_m' => $suggestion['distance_m'] ?? null,
                'suggested_shahidar_id' => $suggestion['shahidar_id'] ?? null,
            ]);
            $customer->forceFill(['status' => CustomerStatus::Active])->save();

            return $pairing;
        });
    }

    /** The customer no longer wants regular food. */
    public static function stop(Customer $customer, User $by, string $reason): void
    {
        DB::transaction(function () use ($customer, $by, $reason) {
            $customer->pairing()->first()?->forceFill(['ended_at' => now(), 'end_reason' => $reason, 'ended_by' => $by->id])->save();
            $customer->forceFill(['status' => CustomerStatus::Stopped])->save();
        });
    }
}
