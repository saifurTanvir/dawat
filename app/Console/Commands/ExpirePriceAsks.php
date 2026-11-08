<?php

namespace App\Console\Commands;

use App\Services\Orders\BookingPlanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-asks')]
#[Description('Release boxes whose customer did not answer about a higher price in time (F30)')]
class ExpirePriceAsks extends Command
{
    public function handle(BookingPlanner $planner): int
    {
        $count = $planner->expireAsks();
        if ($count) {
            $this->info("Released {$count} box(es).");
        }

        return self::SUCCESS;
    }
}
