<?php

namespace App\Jobs;

use App\Models\MessengerThread;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * F80: answers a chat a few seconds after its newest message, so a burst of
 * messages gets one answer. A later message's job takes over from this one.
 */
class AnswerMessenger implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $threadId, public int $lastMessageId) {}

    public function handle(Agent $agent): void
    {
        if (! Graph::botOn()) {
            return;
        }
        $thread = MessengerThread::find($this->threadId);
        $newest = $thread?->messages()->where('direction', 'in')->max('id');
        if (! $thread || (int) $newest !== $this->lastMessageId) {
            return; // a newer message will be answered by its own job
        }

        Cache::lock("messenger-answer:{$thread->id}", 120)->block(30, function () use ($agent, $thread) {
            $fresh = $thread->fresh();
            // Already answered meanwhile (from the panel's "answer now"): do not answer twice.
            if ($fresh->last_out_at && $fresh->last_in_at && $fresh->last_out_at->gte($fresh->last_in_at)) {
                return;
            }
            $agent->answer($fresh);
        });
    }
}
