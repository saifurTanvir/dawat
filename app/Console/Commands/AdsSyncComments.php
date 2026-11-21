<?php

namespace App\Console\Commands;

use App\Models\AdLead;
use App\Services\Ads\BaddaAds;
use App\Services\Messenger\Graph;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** F93: reads comments on the Badda ad posts (with the page token) and whether the page answered each. */
class AdsSyncComments extends Command
{
    protected $signature = 'ads:sync-comments';

    protected $description = 'Store comments on the Badda ad posts for the ads report';

    public function handle(): int
    {
        if (! Graph::configured()) {
            $this->warn('Messenger page token not set');

            return self::SUCCESS;
        }
        $graph = rtrim((string) config('services.messenger.graph'), '/');
        $token = Graph::pageToken();
        $since = BaddaAds::launch();

        $posts = Http::timeout(15)->get($graph.'/'.Graph::PAGE_ID.'/ads_posts', [
            'fields' => 'id,message,created_time', 'limit' => 50, 'access_token' => $token,
        ]);
        if (! $posts->ok()) {
            Cache::put('ads:comments-error', mb_substr((string) $posts->json('error.message', 'HTTP '.$posts->status()), 0, 200), now()->addDay());
            $this->warn('ads_posts failed: '.$posts->status());

            return self::SUCCESS;
        }
        Cache::forget('ads:comments-error');

        $found = 0;
        foreach ((array) $posts->json('data', []) as $post) {
            $ad = collect(BaddaAds::ADS)->search(fn ($a) => str_contains((string) ($post['message'] ?? ''), $a['post']));
            if (! $ad) {
                continue;
            }
            $comments = Http::timeout(15)->get($graph.'/'.$post['id'].'/comments', [
                'fields' => 'id,from{id,name},message,created_time,comments.limit(10){from{id}}',
                'filter' => 'toplevel', 'limit' => 100, 'access_token' => $token,
            ]);
            foreach ((array) $comments->json('data', []) as $c) {
                $at = Carbon::parse($c['created_time'] ?? 'now');
                if ($at->lt($since) || ($c['from']['id'] ?? null) === Graph::PAGE_ID) {
                    continue;
                }
                $replied = collect($c['comments']['data'] ?? [])->contains(fn ($r) => ($r['from']['id'] ?? null) === Graph::PAGE_ID);
                $lead = AdLead::query()->firstOrNew(['channel' => 'comment', 'key' => (string) $c['id']]);
                $lead->fill([
                    'ad' => $ad, 'name' => mb_substr((string) ($c['from']['name'] ?? ''), 0, 120) ?: null,
                    'text' => mb_substr((string) ($c['message'] ?? ''), 0, 500), 'first_at' => $at,
                    'replied_at' => $replied ? ($lead->replied_at ?? now()) : null,
                ])->save();
                $found++;
            }
        }
        Cache::put('ads:comments-synced', now()->toIso8601String(), now()->addDays(7));
        $this->line("comments stored: {$found}");

        return self::SUCCESS;
    }
}
