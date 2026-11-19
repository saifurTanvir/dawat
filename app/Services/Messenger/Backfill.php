<?php

namespace App\Services\Messenger;

use App\Models\MessengerThread;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * F80: brings in chats from before the webhook was connected, so the bot can
 * answer the ones still waiting. Only chats Meta still lets us answer (last
 * 23 hours) and only people we do not have yet — a chat already here is kept
 * as the webhook saved it.
 */
class Backfill
{
    /** @return array{added: int, waiting: int, error: ?string} */
    public function run(int $hours = 23, int $maxPages = 20): array
    {
        Graph::healToken();
        $graph = rtrim((string) config('services.messenger.graph'), '/');
        $token = Graph::pageToken();
        $since = now()->subHours($hours);

        try {
            $page = Http::timeout(15)->get($graph.'/me', ['fields' => 'id', 'access_token' => $token]);
        } catch (ConnectionException) {
            return ['added' => 0, 'waiting' => 0, 'error' => 'Meta-র সার্ভারে পৌঁছানো যায়নি'];
        }
        $pageId = (string) $page->json('id');
        if ($page->failed() || $pageId === '') {
            return ['added' => 0, 'waiting' => 0, 'error' => 'Meta: '.($page->json('error.message') ?: 'পেজ পাওয়া যায়নি')];
        }

        $added = 0;
        $waiting = 0;
        $url = $graph.'/me/conversations';
        $query = [
            'platform' => 'messenger', 'limit' => 25, 'access_token' => $token,
            'fields' => 'updated_time,participants,messages.limit(15){id,message,from,created_time,attachments}',
        ];

        for ($i = 0; $i < $maxPages && $url; $i++) {
            try {
                $r = Http::timeout(30)->get($url, $query);
            } catch (ConnectionException) {
                return ['added' => $added, 'waiting' => $waiting, 'error' => 'Meta-র সার্ভারে পৌঁছানো যায়নি'];
            }
            if ($r->failed()) {
                Log::warning('Messenger backfill failed', ['status' => $r->status(), 'error' => $r->json('error.message')]);

                return ['added' => $added, 'waiting' => $waiting, 'error' => 'Meta: '.($r->json('error.message') ?: 'error '.$r->status())];
            }

            $old = false;
            foreach ((array) $r->json('data', []) as $conv) {
                if (Carbon::parse($conv['updated_time'] ?? '2000-01-01')->lt($since)) {
                    $old = true;

                    break;
                }
                $result = $this->conversation((array) $conv, $pageId);
                $added += $result ? 1 : 0;
                $waiting += $result === 'waiting' ? 1 : 0;
            }
            if ($old) {
                break;
            }
            // Page on with our own query and Meta's cursor (its "next" link can lose the app).
            $after = $r->json('paging.cursors.after');
            if (! $after || ! $r->json('paging.next')) {
                break;
            }
            $query['after'] = $after;
        }

        return ['added' => $added, 'waiting' => $waiting, 'error' => null];
    }

    /** @return 'waiting'|'answered'|null null when skipped (already here, or no person) */
    private function conversation(array $conv, string $pageId): ?string
    {
        $person = collect($conv['participants']['data'] ?? [])->first(fn ($p) => (string) ($p['id'] ?? '') !== $pageId);
        $psid = (string) ($person['id'] ?? '');
        if ($psid === '' || MessengerThread::where('psid', $psid)->exists()) {
            return null;
        }

        // Meta lists newest first; save oldest first so the chat reads in order.
        $messages = array_reverse((array) ($conv['messages']['data'] ?? []));
        if (! $messages) {
            return null;
        }

        $thread = MessengerThread::create(['psid' => $psid, 'name' => isset($person['name']) ? mb_substr((string) $person['name'], 0, 120) : null]);
        $lastIn = null;
        $lastOut = null;
        foreach ($messages as $m) {
            $out = (string) ($m['from']['id'] ?? '') === $pageId;
            $at = Carbon::parse($m['created_time'] ?? 'now');
            $attachments = collect($m['attachments']['data'] ?? [])->map(fn ($a) => [
                'type' => str_starts_with((string) ($a['mime_type'] ?? ''), 'image/') ? 'image' : 'file',
                'payload' => [],
            ])->values()->all();
            if (isset($m['id']) && $thread->messages()->where('mid', $m['id'])->exists()) {
                continue;
            }
            $thread->messages()->create([
                'direction' => $out ? 'out' : 'in', 'by' => $out ? 'page' : 'person',
                'mid' => isset($m['id']) ? mb_substr((string) $m['id'], 0, 120) : null,
                'text' => isset($m['message']) ? mb_substr((string) $m['message'], 0, 5000) : null,
                'attachments' => $attachments ?: null,
                'created_at' => $at,
            ]);
            if (! $out) {
                $lastIn = $at;
            } elseif (! $lastIn || $at->diffInSeconds($lastIn, true) > 20) {
                // A page reply within seconds of theirs is Meta's automatic greeting, not an answer.
                $lastOut = $at;
            }
        }
        $thread->forceFill(['last_in_at' => $lastIn, 'last_out_at' => $lastOut])->save();

        return $lastIn && (! $lastOut || $lastOut->lt($lastIn)) ? 'waiting' : 'answered';
    }
}
