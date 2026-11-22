<?php

namespace App\Console\Commands;

use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use Illuminate\Console\Command;

/** F100: a 10:30 nudge to customer-ad chats that stopped before giving an address. */
class MessengerCustomerFollowUps extends Command
{
    protected $signature = 'messenger:customer-followups';

    protected $description = 'Morning nudge to would-be customers who have not given an address';

    public function handle(Agent $agent): int
    {
        if (! Graph::botOn() || ! Graph::configured()) {
            return self::SUCCESS;
        }
        $this->info($agent->customerFollowUps().' nudges sent');

        return self::SUCCESS;
    }
}
