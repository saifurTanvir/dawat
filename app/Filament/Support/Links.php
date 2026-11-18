<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\User;

/** F49: every name in the admin panel leads to that person's or thing's page. */
final class Links
{
    /** F69: a link only where the signed-in person may go. */
    private static function url(string $resource, string $page, array $params): ?string
    {
        return $resource::canAccess() ? $resource::getUrl($page, $params) : null;
    }

    public static function order(?Order $o): ?string
    {
        return $o ? self::url(OrderResource::class, 'view', ['record' => $o]) : null;
    }

    public static function kitchen(?int $id): ?string
    {
        return $id ? self::url(KitchenResource::class, 'view', ['record' => $id]) : null;
    }

    public static function shahidar(?int $id): ?string
    {
        return $id ? self::url(ShahidarResource::class, 'view', ['record' => $id]) : null;
    }

    public static function user(?int $id): ?string
    {
        return $id ? self::url(UserResource::class, 'view', ['record' => $id]) : null;
    }

    /** A regular customer's page, or the buyer's user page for a feed order. */
    public static function recipient(Order $o): ?string
    {
        return $o->customer_id ? self::url(CustomerResource::class, 'view', ['record' => $o->customer_id]) : self::user($o->buyer_id);
    }

    public static function post(?int $id): ?string
    {
        return $id ? self::url(PostResource::class, 'view', ['record' => $id]) : null;
    }

    public static function bill(?int $id): ?string
    {
        return $id ? self::url(BillResource::class, 'view', ['record' => $id]) : null;
    }

    /** Where a person's history is: shahidar page, else customer page, else user page. */
    public static function person(?User $u): ?string
    {
        return match (true) {
            $u === null => null,
            $u->isShahidar() => self::shahidar($u->id),
            $u->customer !== null => self::url(CustomerResource::class, 'view', ['record' => $u->customer->id]),
            $u->kitchen !== null => self::kitchen($u->kitchen->id),
            default => self::user($u->id),
        };
    }
}
