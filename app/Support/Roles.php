<?php

namespace App\Support;

/**
 * Role names used with spatie/laravel-permission.
 * A shahidar role will be added here in a later phase without schema changes.
 */
final class Roles
{
    public const ADMIN = 'admin';

    public const COOK = 'cook';

    public const BUYER = 'buyer';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ADMIN, self::COOK, self::BUYER];
    }
}
