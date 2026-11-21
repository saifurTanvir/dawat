<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnswerMessenger;
use App\Models\MessengerThread;
use App\Services\Messenger\Graph;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * F80: Meta calls this for every Messenger event on the page.
 * GET = Meta checking the URL (verify token); POST = messages, signed with the app secret.
 */
class MessengerWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $ok = $request->query('hub_mode') === 'subscribe' && hash_equals(Graph::verifyToken(), (string) $request->query('hub_verify_token'));

        return $ok ? response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']) : response('', 403);
    }

    public function receive(Request $request, Graph $graph): Response
    {
        abort_unless(Graph::signed($request->getContent(), $request->header('X-Hub-Signature-256')), 403);
        Cache::put('messenger:last-webhook', now()->toIso8601String(), now()->addDays(30));

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $this->event((array) $event, $graph);
            }
        }

        return response('EVENT_RECEIVED');
    }

    private function event(array $event, Graph $graph): void
    {
        $message = $event['message'] ?? null;
        $echo = (bool) ($message['is_echo'] ?? false);
        // For an echo (sent from the page) the person is the recipient.
        $psid = (string) ($echo ? ($event['recipient']['id'] ?? '') : ($event['sender']['id'] ?? ''));
        if ($psid === '' && isset($event['referral'])) {
            $psid = (string) ($event['sender']['id'] ?? '');
        }
        if ($psid === '' || (! $message && ! isset($event['postback']) && ! isset($event['referral']))) {
            return;
        }

        $thread = MessengerThread::firstOrCreate(['psid' => $psid]);

        // F93: a chat started from an ad carries the ad id — remember it for the ads report.
        $referral = $event['referral'] ?? $event['postback']['referral'] ?? $message['referral'] ?? null;
        if (! $echo && is_array($referral) && filled($referral['ad_id'] ?? null)) {
            $thread->facts = [...($thread->facts ?? []), 'ad_id' => (string) $referral['ad_id'], 'ad_at' => now()->toIso8601String()];
            $thread->save();
        }
        if (! $thread->name && Graph::configured() && ! $echo) {
            $thread->forceFill(['name' => $graph->name($psid)])->save();
        }

        if ($echo) {
            $ours = ($message['metadata'] ?? null) === Graph::METADATA;
            if ($ours) {
                return; // already saved when the bot sent it
            }
            $this->save($thread, 'out', 'page', $message);
            // Someone typed on the page itself (not Meta's instant auto-reply): let them talk, the bot waits.
            // No message from them yet, or a reply within seconds of theirs = Meta's automatic greeting, not a person.
            $instant = ! $thread->last_in_at || $thread->last_in_at->gt(now()->subSeconds(15));
            if (! $instant) {
                $thread->forceFill(['last_out_at' => now(), 'paused_until' => now()->addHours(MessengerThread::PAUSE_HOURS)])->save();
            }

            return;
        }

        if (! $message && ! isset($event['postback'])) {
            return; // only a referral: noted above
        }

        $saved = $message
            ? $this->save($thread, 'in', 'person', $message)
            : $this->save($thread, 'in', 'person', ['mid' => 'pb-'.($event['timestamp'] ?? microtime(true)), 'text' => $event['postback']['title'] ?? $event['postback']['payload'] ?? '']);
        if (! $saved) {
            return; // Meta sent the same message twice
        }
        $thread->forceFill(['last_in_at' => now()])->save();

        if (Graph::botOn() && Graph::configured()) {
            AnswerMessenger::dispatch($thread->id, $saved)->delay(now()->addSeconds((int) config('services.messenger.wait', 6)));
        }
    }

    /** @return int|null the new message's id, null if it was already saved */
    private function save(MessengerThread $thread, string $direction, string $by, array $message): ?int
    {
        $attachments = collect($message['attachments'] ?? [])->map(fn ($a) => [
            'type' => $a['type'] ?? null,
            'payload' => array_intersect_key((array) ($a['payload'] ?? []), array_flip(['url', 'coordinates', 'title', 'sticker_id'])),
        ])->values()->all();

        try {
            return $thread->messages()->create([
                'direction' => $direction, 'by' => $by,
                'mid' => isset($message['mid']) ? mb_substr((string) $message['mid'], 0, 120) : null,
                'text' => isset($message['text']) ? mb_substr((string) $message['text'], 0, 5000) : null,
                'attachments' => $attachments ?: null,
            ])->id;
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
