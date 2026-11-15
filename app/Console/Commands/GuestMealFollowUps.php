<?php

namespace App\Console\Commands;

use App\Services\Regular\GuestMeals;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('guests:follow-up')]
#[Description('Guest meals the cook has not answered in 5 minutes: tell the shahidar and admins (F64)')]
class GuestMealFollowUps extends Command
{
    public function handle(GuestMeals $guests): int
    {
        $n = $guests->followUps();
        if ($n) {
            $this->info("Followed up {$n} guest meal(s).");
        }

        return self::SUCCESS;
    }
}
