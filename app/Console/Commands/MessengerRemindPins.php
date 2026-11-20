<?php

namespace App\Console\Commands;

use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use Illuminate\Console\Command;

/** Sends each pinless cook who wrote within 24 hours her own location link (at most every 12 hours). */
class MessengerRemindPins extends Command
{
    protected $signature = 'messenger:remind-pins';

    protected $description = 'Send pinless cooks their own location link on Messenger';

    public function handle(Agent $agent): int
    {
        if (! Graph::botOn() || ! Graph::configured()) {
            return self::SUCCESS;
        }
        $this->info($agent->remindPins().' reminders sent');

        return self::SUCCESS;
    }
}
