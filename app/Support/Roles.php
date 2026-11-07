<?php

namespace App\Support;

/**
 * Role names used with spatie/laravel-permission.
 */
final class Roles
{
    public const ADMIN = 'admin';

    public const COOK = 'cook';

    public const BUYER = 'buyer';

    /** Trusted local runner: picks up from cooks, delivers to regular customers (F24). */
    public const SHAHIDAR = 'shahidar';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ADMIN, self::COOK, self::BUYER, self::SHAHIDAR];
    }
}
