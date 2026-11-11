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
 */
class PairCustomer
{
    public function __invoke(Customer $customer, Kitchen $kitchen, User $shahidar, User $by, ?string $reason = null): Pairing
    {
        $pairing = $this->pair($customer, $kitchen, $shahidar, $by, $reason);

        // F45: if the cook has already posted today, the customer gets a box from it now.
        app(BookingPlanner::class)->bookLate($customer);

        return $pairing;
    }

    private function pair(Customer $customer, Kitchen $kitchen, User $shahidar, User $by, ?string $reason): Pairing
    {
        return DB::transaction(function () use ($customer, $kitchen, $shahidar, $by, $reason) {
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
            if (! $sameCook && $kitchen->pairings()->count() >= $kitchen->max_regular_customers) {
                throw ValidationException::withMessages(['kitchen_id' => "এই গৃহিণীর নিয়মিত গ্রাহকের জায়গা ভরা ({$kitchen->max_regular_customers} জন)।"]);
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
