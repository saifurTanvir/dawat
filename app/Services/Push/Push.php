<?php

namespace App\Services\Push;

use App\Jobs\SendWebPush;
use App\Models\User;

/**
 * F37: free notifications to a person's phone (Web Push through the browser
 * or the installed app). Nothing happens for people who never turned them on.
 */
class Push
{
    public static function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /** @param  string  $url  path inside the app to open on tap, e.g. /shahidar */
    public static function toUser(User|int|null $user, string $title, string $body, string $url = '/', ?string $tag = null): void
    {
        $id = $user instanceof User ? $user->id : $user;
        if (! $id || ! self::enabled()) {
            return;
        }

        SendWebPush::dispatch($id, $title, $body, $url, $tag)->afterCommit();
    }
}
