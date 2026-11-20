<?php

namespace App\Jobs;

use App\Models\MessengerThread;
use App\Services\Ai\AssistantUnavailableException;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * F80: answers a chat a few seconds after its newest message, so a burst of
 * messages gets one answer. A later message's job takes over from this one.
 */
class AnswerMessenger implements ShouldQueue
{
    use Queueable;

    /** A busy AI is tried again after these many seconds; the last try falls back to the greeting. */
    public const BACKOFF = [20, 60, 180];

    public int $tries = 4;

    /** @param  bool  $force  answer even if the chat looks answered (a retry of a chat the bot gave up on) */
    public function __construct(public int $threadId, public int $lastMessageId, public bool $force = false)
    {
        $this->queuedAt = now()->toIso8601String();
    }

    public string $queuedAt;

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
            // Answered since this job was queued (another copy, or the panel): never twice.
            if ($fresh->last_out_at && $fresh->last_out_at->gt(Carbon::parse($this->queuedAt))) {
                return;
            }
            if (! $this->force && $fresh->last_out_at && $fresh->last_in_at && $fresh->last_out_at->gte($fresh->last_in_at)) {
                return;
            }
            try {
                $agent->answer($fresh, final: $this->attempts() >= $this->tries);
            } catch (AssistantUnavailableException $e) {
                $this->release(self::BACKOFF[$this->attempts() - 1] ?? 180);
            }
        });
    }
}
