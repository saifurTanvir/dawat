<?php

namespace Tests\Support;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Pairing;
use App\Models\User;

/**
 * F55 allows one regular customer per cook. Cooks paired with several customers
 * before that rule still exist and still get booked, so tests that need such a
 * cook add the extra customers directly, the way the old data looks.
 */
trait PairsLikeBeforeF55
{
    protected function pairAllowingShared(Customer $customer, Kitchen $kitchen, User $shahidar, User $admin): void
    {
        if ($kitchen->isFreeForRegular($customer)) {
            app(PairCustomer::class)($customer, $kitchen, $shahidar, $admin);

            return;
        }

        Pairing::create([
            'customer_id' => $customer->id, 'kitchen_id' => $kitchen->id, 'shahidar_id' => $shahidar->id,
            'started_at' => now(), 'created_by' => $admin->id,
        ]);
        $customer->forceFill(['status' => CustomerStatus::Active])->save();
    }
}
