<?php

namespace App\Console\Commands;

use App\Jobs\AnswerMessenger;
use App\Models\MessengerThread;
use App\Services\Messenger\Agent;
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
        // Chats from an ad may be answered for up to 7 days; others fail once and are left (send_error).
        MessengerThread::query()->waiting()->where('last_in_at', '>', now()->subHours(166))->where('last_in_at', '<', now()->subMinutes(3))
            ->where('bot_off', false)->where(fn ($q) => $q->whereNull('paused_until')->orWhere('paused_until', '<', now()))
            ->whereNull('send_error')->limit(40)
            ->each(function (MessengerThread $t) use (&$n) {
                AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'))->delay(now()->addSeconds(3 * $n++));
            });
        // Chats the bot gave up on (AI busy) or could not place (unknown area) get one more go, while Meta still lets us answer.
        MessengerThread::query()->where('needs_human', true)->where('last_in_at', '>', now()->subHours(23))
            ->where(fn ($q) => $q->where('human_reason', 'like', Agent::AI_FAILED.'%')->orWhere('human_reason', 'like', Agent::NO_AREA.'%'))
            ->each(function (MessengerThread $t) use (&$n) {
                $facts = $t->facts ?? [];
                $tries = (int) ($facts['bot_retries'] ?? 0);
                if ($tries >= 2) {
                    return; // left for a person
                }
                $t->forceFill(['needs_human' => false, 'human_reason' => null, 'facts' => [...$facts, 'bot_retries' => $tries + 1]])->save();
                AnswerMessenger::dispatch($t->id, (int) $t->messages()->where('direction', 'in')->max('id'), force: true)->delay(now()->addSeconds(3 * $n++));
            });
        $this->info("{$n} chats queued");

        return self::SUCCESS;
    }
}
