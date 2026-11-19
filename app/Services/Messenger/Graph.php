<?php

namespace App\Services\Messenger;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** F80: the little of Meta's Graph API the bot needs: send a text, read a name. */
class Graph
{
    /** Marks our own sends, so their echoes are not taken for a person on the page. */
    public const METADATA = 'dawat-bot';

    /** The admin's on/off switch for the bot (kept in app_settings). */
    public static function botOn(): bool
    {
        return (bool) (AppSetting::get('messenger')['auto_reply'] ?? false);
    }

    public static function setBot(bool $on, ?User $by = null): void
    {
        AppSetting::put('messenger', [...AppSetting::get('messenger'), 'auto_reply' => $on], $by);
    }

    public static function configured(): bool
    {
        return filled(self::pageToken()) && filled(config('services.messenger.app_secret'));
    }

    /** The page token an admin saved in the panel (kept encrypted), else the one in .env. */
    public static function pageToken(): string
    {
        $saved = AppSetting::get('messenger')['page_token'] ?? null;
        if (is_string($saved) && $saved !== '') {
            try {
                return Crypt::decryptString($saved);
            } catch (DecryptException) {
                // fall back to .env
            }
        }

        return (string) config('services.messenger.page_token');
    }

    public static function savePageToken(string $token, ?User $by = null): void
    {
        AppSetting::put('messenger', [...AppSetting::get('messenger'), 'page_token' => Crypt::encryptString(trim($token))], $by);
    }

    /**
     * If the saved token is a user token, swap it for the page's own token.
     * True when a different token was saved.
     */
    public static function healToken(): bool
    {
        $current = self::pageToken();
        if ($current === '') {
            return false;
        }
        $check = self::checkToken($current);
        if ($check['ok'] && $check['token'] && $check['token'] !== $current) {
            self::savePageToken($check['token']);

            return true;
        }

        return false;
    }

    /** The Facebook page the bot answers for. */
    public const PAGE_ID = '1482964881559150';

    /**
     * What Meta says about a token. A user token (Graph API Explorer's default)
     * is swapped for the page's own token, so pasting either one works.
     *
     * @return array{ok: bool, name: ?string, error: ?string, token: ?string}
     */
    public static function checkToken(string $token): array
    {
        $token = trim($token);
        $graph = config('services.messenger.graph');
        $fail = fn (string $e) => ['ok' => false, 'name' => null, 'error' => $e, 'token' => null];
        try {
            $me = Http::timeout(10)->get($graph.'/me', ['fields' => 'id,name', 'metadata' => 1, 'access_token' => $token]);
            if ($me->failed() || ! $me->json('id')) {
                return $fail('Meta: '.($me->json('error.message') ?: 'error '.$me->status()));
            }
            if ($me->json('metadata.type') === 'page' || (string) $me->json('id') === self::PAGE_ID) {
                return ['ok' => true, 'name' => $me->json('name'), 'error' => null, 'token' => $token];
            }
            // A user token: take this page's token from the pages the person manages.
            $pages = Http::timeout(10)->get($graph.'/me/accounts', ['fields' => 'id,name,access_token', 'limit' => 100, 'access_token' => $token]);
        } catch (ConnectionException) {
            return $fail('Meta-র সার্ভারে পৌঁছানো যায়নি');
        }
        $page = collect($pages->json('data', []))->first(fn ($p) => (string) ($p['id'] ?? '') === self::PAGE_ID)
            ?? (count($pages->json('data', [])) === 1 ? $pages->json('data.0') : null);
        if (! $page || empty($page['access_token'])) {
            return $fail('এটা পেজের টোকেন নয়, আর এই টোকেন দিয়ে দাওয়াত পেজটা পাওয়া গেল না। Explorer-এ "User or Page" থেকে দাওয়াত পেজ বেছে নিন।');
        }

        return ['ok' => true, 'name' => $page['name'] ?? null, 'error' => null, 'token' => $page['access_token']];
    }

    public static function verifyToken(): string
    {
        return (string) (config('services.messenger.verify_token')
            ?: substr(hash_hmac('sha256', 'messenger-webhook', (string) config('app.key')), 0, 32));
    }

    /** True when Meta signed this body with our app secret. */
    public static function signed(string $body, ?string $header): bool
    {
        $secret = (string) config('services.messenger.app_secret');
        if ($secret === '' || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $header);
    }

    /** @return string|null error text, null when sent */
    public function send(string $psid, string $text): ?string
    {
        return $this->post(['recipient' => ['id' => $psid], 'messaging_type' => 'RESPONSE', 'message' => ['text' => mb_substr($text, 0, 1900), 'metadata' => self::METADATA]]);
    }

    public function typing(string $psid): void
    {
        $this->post(['recipient' => ['id' => $psid], 'sender_action' => 'typing_on']);
    }

    /** Their Facebook name, when Meta shares it. */
    public function name(string $psid): ?string
    {
        try {
            $r = Http::timeout(8)->get(config('services.messenger.graph').'/'.$psid, [
                'fields' => 'first_name,last_name', 'access_token' => self::pageToken(),
            ]);
        } catch (ConnectionException) {
            return null;
        }
        $name = trim($r->json('first_name', '').' '.$r->json('last_name', ''));

        return $r->successful() && $name !== '' ? mb_substr($name, 0, 120) : null;
    }

    private function post(array $payload, bool $retried = false): ?string
    {
        try {
            $r = Http::timeout(10)->acceptJson()
                ->post(config('services.messenger.graph').'/me/messages?access_token='.urlencode(self::pageToken()), $payload);
        } catch (ConnectionException) {
            return 'Meta-র সার্ভারে পৌঁছানো যায়নি';
        }
        if ($r->failed() && ! $retried && in_array((int) $r->json('error.code'), [10, 190, 200], true) && self::healToken()) {
            return $this->post($payload, true);
        }
        if ($r->failed()) {
            Log::warning('Messenger send failed', ['status' => $r->status(), 'code' => $r->json('error.code'), 'sub' => $r->json('error.error_subcode')]);

            return mb_substr('Meta: '.($r->json('error.message') ?: 'error '.$r->status()), 0, 250);
        }

        return null;
    }
}
