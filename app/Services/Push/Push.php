<?php

namespace App\Services\Push;

use App\Jobs\SendWebPush;
use App\Models\AppNotification;
use App\Models\User;

/**
 * F37: free notifications to a person's phone (Web Push through the browser
 * or the installed app). Nothing happens on the phone for people who never
 * turned them on. F66: every notification is also kept in the app's own list
 * (🔔), so it can be read there, with what it is about, and opened.
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
        if (! $id) {
            return;
        }

        $note = AppNotification::record($id, $title, $body, $url, $tag);

        if (self::enabled()) {
            SendWebPush::dispatch($id, $title, $body, $url, $tag, $note->id)->afterCommit();
        }
    }
}
