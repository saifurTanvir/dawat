<?php

namespace App\Services\Posts;

use App\Actions\PublishPost;
use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Services\Orders\BookingPlanner;
use App\Support\ContactHours;
use App\Support\DhakaTime;
use Illuminate\Support\Collection;

/**
 * F44: where a post's plates went — regular customers (booked automatically),
 * customers being asked about a higher price, buyers from the feed, and what
 * is still on the feed. Cooks see numbers only; shahidars and the admin also
 * see who.
 */
class PlateSplit
{
    public function __construct(private readonly BookingPlanner $planner) {}

    /** @return array{total: int, regular: int, regular_people: int, asking: int, asking_people: int, feed_orders: int, left: int} */
    public function forPost(Post $post): array
    {
        $orders = $post->relationLoaded('orders') ? $post->orders : $post->orders()->get();
        $live = $orders->reject(fn (Order $o) => $o->status === OrderStatus::Cancelled);
        $regular = $live->filter(fn (Order $o) => ! $o->isOneOff() && $o->status !== OrderStatus::Asking);
        $asking = $live->filter(fn (Order $o) => $o->status === OrderStatus::Asking);
        $feed = $live->filter(fn (Order $o) => $o->isOneOff());

        return [
            'total' => $post->plates_total,
            'regular' => (int) $regular->sum('plates'),
            'regular_people' => $regular->count(),
            'asking' => (int) $asking->sum('plates'),
            'asking_people' => $asking->count(),
            'feed_orders' => (int) $feed->sum('plates'),
            'left' => $post->plates_left,
        ];
    }

    /**
     * Before posting: what will happen with this many plates at this price.
     *
     * @return array{needed: int, people: int, asking_people: int, to_feed: int, enough: bool, line: string}
     */
    public function preview(Kitchen $kitchen, MealSlot $slot, int $plates, ?int $pricePaisa = null): array
    {
        $pairings = $this->planner->expected($kitchen, $slot);
        $needed = (int) $pairings->sum(fn (Pairing $p) => $p->customer->plates);
        $asking = $pricePaisa === null ? 0 : $pairings->filter(fn (Pairing $p) => $p->customer->max_price_paisa !== null && $pricePaisa > $p->customer->max_price_paisa)->count();
        $toFeed = max(0, $plates - $needed);
        $bn = fn (int $n) => Order::banglaDigits((string) $n);

        $line = match (true) {
            $needed === 0 => "আজ {$slot->label()} নিয়মিত কোনো বুকিং নেই — সব {$bn($plates)} প্লেট ফিডে যাবে।",
            $plates < $needed => "নিয়মিত গ্রাহকদের {$bn($needed)} প্লেট লাগবে — অন্তত {$bn($needed)} প্লেট দিন।",
            default => "নিয়মিত গ্রাহক {$bn($pairings->count())} জন — {$bn($needed)} প্লেট আগে যাবে। ফিডে যাবে {$bn($toFeed)} প্লেট।",
        };
        if ($asking && $plates >= $needed) {
            $line .= " {$bn($asking)} জনের দামের সীমা কম, তাঁদের আগে জিজ্ঞেস করা হবে।";
        }

        return ['needed' => $needed, 'people' => $pairings->count(), 'asking_people' => $asking, 'to_feed' => $toFeed, 'enough' => $plates >= $needed, 'line' => $line];
    }

    /** "নিয়মিত ৩ · জিজ্ঞেস ১ · ফিডে অর্ডার ১ · বাকি ১" */
    public static function line(array $s): string
    {
        $bn = fn (int $n) => Order::banglaDigits((string) $n);
        $parts = [];
        if ($s['regular']) {
            $parts[] = "নিয়মিত {$bn($s['regular'])}";
        }
        if ($s['asking']) {
            $parts[] = "দাম জিজ্ঞেস {$bn($s['asking'])}";
        }
        if ($s['feed_orders']) {
            $parts[] = "ফিডে অর্ডার {$bn($s['feed_orders'])}";
        }
        $parts[] = "ফিডে বাকি {$bn($s['left'])}";

        return implode(' · ', $parts);
    }

    /** The result right after posting, numbers only (for the cook). */
    public static function resultLine(array $s): string
    {
        $bn = fn (int $n) => Order::banglaDigits((string) $n);
        $parts = [];
        if ($s['regular']) {
            $parts[] = "{$bn($s['regular'])} প্লেট নিয়মিত গ্রাহকদের বুক হয়েছে";
        }
        if ($s['asking_people']) {
            $parts[] = "{$bn($s['asking_people'])} জনকে দাম জিজ্ঞেস করা হচ্ছে";
        }
        $parts[] = "{$bn($s['left'])} প্লেট ফিডে গেছে";

        return implode(' · ', $parts).'।';
    }

    /** For shahidars and the admin: who got a box from this post. @return Collection<int, array> */
    public function people(Post $post): Collection
    {
        return $post->orders()->with(['customer.user', 'buyer', 'shahidar'])->orderBy('seq')->get()->map(fn (Order $o) => [
            'code' => $o->code(),
            'name' => $o->recipient()?->name ?: 'ক্রেতা',
            'kind' => $o->isOneOff() ? 'ফিড থেকে' : 'নিয়মিত',
            'plates' => $o->plates,
            'status' => $o->status->label(),
            'shahidar' => $o->shahidar?->name,
            'shahidar_id' => $o->shahidar_id,
        ]);
    }

    /**
     * For the admin dashboard: every cook and meal that matters today, whether
     * the food is posted (and by whom), and where the plates went.
     *
     * @return list<array<string, mixed>>
     */
    public function today(): array
    {
        $start = now(DhakaTime::TZ)->startOfDay();
        $kitchens = Kitchen::where('status', KitchenStatus::Approved)
            ->with(['menus', 'posts' => fn ($q) => $q->where('published_at', '>=', $start)->with(['orders', 'postedBy'])])
            ->get();
        $rows = [];

        foreach ($kitchens as $kitchen) {
            foreach (MealSlot::cases() as $slot) {
                $posts = $kitchen->posts->where('meal_slot', $slot);
                $menu = $kitchen->menus->first(fn ($m) => $m->meal_slot === $slot && $m->active);
                $expected = $this->planner->expected($kitchen, $slot);
                if ($posts->isEmpty() && ! $menu && $expected->isEmpty()) {
                    continue;
                }

                $sum = ['regular' => 0, 'regular_people' => 0, 'asking' => 0, 'asking_people' => 0, 'feed_orders' => 0, 'left' => 0, 'total' => 0];
                foreach ($posts as $post) {
                    foreach ($this->forPost($post) as $k => $v) {
                        $sum[$k] += $v;
                    }
                }
                $first = $posts->sortBy('published_at')->first();
                $status = match (true) {
                    $first !== null => 'পোস্ট হয়েছে '.ContactHours::clock($first->published_at->timezone(DhakaTime::TZ)->format('H:i'))
                        .' · '.(PublishPost::SOURCES[$first->source ?? 'cook'] ?? '')
                        .($first->source !== 'cook' && $first->postedBy ? ' ('.$first->postedBy->name.')' : ''),
                    PostForCook::offToday($kitchen->id, $slot) => 'আজ রান্না নেই',
                    $menu && str_contains(PostForCook::menuToday($menu), 'নিজে পোস্ট হবে') => 'মেনু থেকে '.ContactHours::clock($menu->postAt()).'-এ পোস্ট হবে',
                    default => 'এখনো পোস্ট আসেনি',
                };

                $rows[] = [
                    'kitchen' => $kitchen,
                    'slot' => $slot,
                    'posted' => $first !== null,
                    'status' => $status,
                    'waiting_people' => $expected->count(),
                    'waiting_plates' => (int) $expected->sum(fn (Pairing $p) => $p->customer->plates),
                ] + $sum;
            }
        }

        usort($rows, fn ($a, $b) => [array_search($a['slot'], MealSlot::cases()), $a['posted'], $a['kitchen']->display_name]
            <=> [array_search($b['slot'], MealSlot::cases()), $b['posted'], $b['kitchen']->display_name]);

        return $rows;
    }
}
