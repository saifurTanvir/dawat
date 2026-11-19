<?php

namespace App\Services\Messenger;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
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
        return filled(config('services.messenger.page_token')) && filled(config('services.messenger.app_secret'));
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
                'fields' => 'first_name,last_name', 'access_token' => config('services.messenger.page_token'),
            ]);
        } catch (ConnectionException) {
            return null;
        }
        $name = trim($r->json('first_name', '').' '.$r->json('last_name', ''));

        return $r->successful() && $name !== '' ? mb_substr($name, 0, 120) : null;
    }

    private function post(array $payload): ?string
    {
        try {
            $r = Http::timeout(10)->acceptJson()
                ->post(config('services.messenger.graph').'/me/messages?access_token='.urlencode((string) config('services.messenger.page_token')), $payload);
        } catch (ConnectionException) {
            return 'Meta-র সার্ভারে পৌঁছানো যায়নি';
        }
        if ($r->failed()) {
            Log::warning('Messenger send failed', ['status' => $r->status(), 'code' => $r->json('error.code'), 'sub' => $r->json('error.error_subcode')]);

            return mb_substr('Meta: '.($r->json('error.message') ?: 'error '.$r->status()), 0, 250);
        }

        return null;
    }
}
