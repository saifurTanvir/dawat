<?php

namespace App\Services\Admin;

use App\Http\Controllers\Api\V1\ShahidarController;
use App\Models\Impersonation;
use App\Models\User;
use App\Services\Push\Push;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * F51: open the app as another person. The admin may open anyone's but an
 * admin's; a shahidar only the cooks they work with. 30 minutes, every
 * change logged, and the person is told.
 */
class Impersonate
{
    public const MINUTES = 30;

    public static function canOpen(User $actor, User $target): bool
    {
        if ($actor->id === $target->id || $target->hasRole(Roles::ADMIN) || $target->isBlocked()) {
            return false;
        }
        if ($actor->hasRole(Roles::ADMIN)) {
            return true;
        }

        return $actor->isShahidar() && $target->kitchen !== null && ShahidarController::worksWith($actor, $target->kitchen);
    }

    /** Returns the URL that opens the app as $target (one-time). */
    public function start(User $actor, User $target): string
    {
        if (! self::canOpen($actor, $target)) {
            throw ValidationException::withMessages(['target' => 'এই মানুষের অ্যাপ আপনি খুলতে পারবেন না।']);
        }
        $code = Str::random(40);
        Impersonation::create([
            'actor_id' => $actor->id,
            'target_id' => $target->id,
            'actor_role' => $actor->hasRole(Roles::ADMIN) ? 'admin' : 'shahidar',
            'claim_hash' => hash('sha256', $code),
            'ends_at' => now()->addMinutes(self::MINUTES),
        ]);

        return url('/as/'.$code);
    }

    /** Exchanges the one-time code for an API token of the target. */
    public function claim(string $code): ?array
    {
        return DB::transaction(function () use ($code) {
            $imp = Impersonation::where('claim_hash', hash('sha256', $code))->lockForUpdate()->first();
            if (! $imp || $imp->claimed_at || ! $imp->isLive() || ! self::canOpen($imp->actor, $imp->target)) {
                return null;
            }
            $token = $imp->target->createToken('impersonation:'.$imp->id, ['*'], $imp->ends_at);
            $imp->forceFill(['claimed_at' => now(), 'token_id' => $token->accessToken->id])->save();

            Push::toUser($imp->target_id, $imp->actorLabel().' আপনার অ্যাপ খুলেছেন',
                'সাহায্যের জন্য আপনার অ্যাপ দেখছেন। যা করবেন তা দাওয়াতের খাতায় লেখা থাকবে।', '/', 'impersonation-'.$imp->id);

            return ['token' => $token->plainTextToken, 'impersonation' => $imp];
        });
    }

    public function end(Impersonation $imp): void
    {
        if ($imp->ended_at) {
            return;
        }
        $imp->forceFill(['ended_at' => now()])->save();
        if ($imp->token_id) {
            PersonalAccessToken::whereKey($imp->token_id)->delete();
        }
        $done = $imp->actions()->where('status', '<', 400)->get();
        if ($done->isNotEmpty()) {
            Push::toUser($imp->target_id, $imp->actorLabel().' আপনার হয়ে কাজ করেছেন',
                $done->map->label()->unique()->implode(', '), '/', 'impersonation-'.$imp->id);
        }
    }

    /** The live session behind the current API token, if any. */
    public static function current(?User $user): ?Impersonation
    {
        $token = $user?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! str_starts_with((string) $token->name, 'impersonation:')) {
            return null;
        }

        return Impersonation::find((int) substr($token->name, strlen('impersonation:')));
    }
}
