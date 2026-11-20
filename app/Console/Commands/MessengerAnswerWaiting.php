<?php

namespace App\Console\Commands;

use App\Jobs\AnswerMessenger;
use App\Models\MessengerThread;
use App\Services\Messenger\Graph;
use Illuminate\Console\Command;

/** Nobody is left waiting: chats whose answer did not go out are tried again. */
class MessengerAnswerWaiting extends Command
{
    protected $signature = 'messenger:answer-waiting';

    protected $description = 'Retry Messenger chats still waiting for an answer';

    public function handle(): int
    {
        if (! Graph::botOn() || ! Graph::configured()) {
            return self::SUCCESS;
        }
        $n = 0;
        MessengerThread::query()->answerable()->where('last_in_at', '<', now()->subMinutes(3))->whereNull('send_error')
            ->each(function (MessengerThread $t) use (&$n) {
                AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'))->delay(now()->addSeconds(3 * $n++));
            });
        $this->info("{$n} chats queued");

        return self::SUCCESS;
    }
}
