<?php

namespace App\Console\Commands;

use App\Services\Orders\Billing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bills:issue')]
#[Description('Weekly bills on Saturday, monthly bills on the 1st (F39)')]
class IssueBills extends Command
{
    public function handle(Billing $billing): int
    {
        $bills = $billing->issueDue();
        if ($bills->isNotEmpty()) {
            $this->info("Issued {$bills->count()} bill(s).");
        }

        return self::SUCCESS;
    }
}
