<?php

namespace App\Services\Kitchens;

use App\Models\Kitchen;
use App\Models\KitchenAction;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Push\Push;
use App\Support\DhakaTime;
use App\Support\Roles;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * F46: the admin or a shahidar does something for a cook. It is written in
 * the kitchen's log and the cook is told every time.
 */
class ActForCook
{
    /**
     * @template T
     *
     * @param  Closure(): T  $do
     * @return T
     */
    public function run(Kitchen $kitchen, User $by, string $action, string $details, Closure $do, ?Post $post = null, ?Order $order = null): mixed
    {
        $result = DB::transaction(function () use ($kitchen, $by, $action, $details, $do, $post, $order) {
            $result = $do();
            KitchenAction::create([
                'kitchen_id' => $kitchen->id,
                'user_id' => $by->id,
                'role' => self::role($by),
                'action' => $action,
                'details' => mb_substr($details, 0, 300),
                'post_id' => $post?->id,
                'order_id' => $order?->id,
            ]);

            return $result;
        });

        $who = self::role($by) === 'shahidar' ? 'শাহিদার '.($by->name ?: '') : 'দাওয়াত টিম';
        Push::toUser($kitchen->user_id, "{$who} আপনার হয়ে কাজ করেছেন", $details, '/cook', 'acted-'.$kitchen->id.'-'.$action);

        return $result;
    }

    public static function role(User $by): string
    {
        return $by->hasRole(Roles::ADMIN) ? 'admin' : 'shahidar';
    }

    /** Today's log for a kitchen, newest first. @return list<array> */
    public static function today(Kitchen $kitchen): array
    {
        return KitchenAction::where('kitchen_id', $kitchen->id)
            ->where('created_at', '>=', now(DhakaTime::TZ)->startOfDay())
            ->with('user')->latest('id')->get()->map->toRow()->all();
    }
}
