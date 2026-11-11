<?php

namespace App\Services\Posts;

use App\Actions\PublishPost;
use App\Enums\KitchenStatus;
use App\Enums\MealSlot;
use App\Models\Kitchen;
use App\Models\KitchenAction;
use App\Models\KitchenDayOff;
use App\Models\KitchenMenu;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Push\Push;
use App\Support\ContactHours;
use App\Support\DhakaTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * F43: today's food posted for a cook — by the Dawat team, by her shahidar
 * after a phone call, or by her daily menu at a set time. She is told every
 * time, and her regular customers get their plates exactly as if she had
 * posted herself.
 */
class PostForCook
{
    public function __construct(private readonly PublishPost $publish) {}

    /**
     * @param  array<string, mixed>  $data  title, price_taka, plates_total, meal_slot, available_hours, note
     * @param  list<UploadedFile>  $images
     */
    public function post(Kitchen $kitchen, array $data, array $images, string $source, ?User $by): Post
    {
        if ($kitchen->status !== KitchenStatus::Approved) {
            throw ValidationException::withMessages(['kitchen' => 'এই রান্নাঘর এখন অনুমোদিত নয়।']);
        }
        if (! $kitchen->isSetUp()) {
            throw ValidationException::withMessages(['kitchen' => 'রান্নাঘরের লোকেশন আর এলাকা সেট হয়নি, তাই পোস্ট করা যাবে না।']);
        }

        $post = $this->publish->handle($kitchen, $data, $images, $source, $by);
        if ($by && in_array($source, ['admin', 'shahidar'], true)) {
            // F46: in the kitchen's log with everything else done for her.
            KitchenAction::create(['kitchen_id' => $kitchen->id, 'user_id' => $by->id, 'role' => $source, 'action' => 'post',
                'details' => "আজকের {$post->meal_slot->label()} পোস্ট: {$post->title}", 'post_id' => $post->id]);
        }

        $who = match ($source) {
            'auto' => 'আপনার প্রতিদিনের মেনু থেকে',
            'shahidar' => 'শাহিদার '.($by?->name ?: '').' আপনার হয়ে',
            default => 'দাওয়াত টিম আপনার হয়ে',
        };
        Push::toUser($kitchen->user_id, 'আজকের '.$post->meal_slot->label().' পোস্ট হয়েছে',
            "{$who} পোস্ট দিয়েছে: {$post->title}। ".PlateSplit::resultLine(app(PlateSplit::class)->forPost($post->load('orders'))), '/cook/posts', 'post-'.$post->id);

        return $post;
    }

    /** Did this kitchen already post this meal today (any status)? */
    public static function postedToday(Kitchen|int $kitchen, MealSlot $slot): bool
    {
        return Post::where('kitchen_id', $kitchen instanceof Kitchen ? $kitchen->id : $kitchen)
            ->where('meal_slot', $slot->value)
            ->where('published_at', '>=', now(DhakaTime::TZ)->startOfDay())
            ->exists();
    }

    public static function offToday(int $kitchenId, MealSlot $slot): bool
    {
        return KitchenDayOff::where('kitchen_id', $kitchenId)->whereDate('date', BookingPlanner::today())
            ->where('meal_slot', $slot->value)->exists();
    }

    /** What happens with this menu today, for the cook's screen. */
    public static function menuToday(KitchenMenu $menu, ?Carbon $now = null): string
    {
        $now = ($now ?? now())->copy()->timezone(DhakaTime::TZ);
        if (! $menu->active) {
            return 'বন্ধ রাখা আছে';
        }
        if (self::postedToday($menu->kitchen_id, $menu->meal_slot)) {
            return 'আজ পোস্ট হয়ে গেছে';
        }
        if (self::offToday($menu->kitchen_id, $menu->meal_slot)) {
            return 'আজ রান্না নেই';
        }
        if (! in_array($now->dayOfWeek, array_map('intval', $menu->weekdays), true)) {
            return 'আজ রান্নার দিন না';
        }

        return $now->format('H:i') < $menu->postAt()
            ? 'আজ '.ContactHours::clock($menu->postAt()).'-এ নিজে পোস্ট হবে'
            : 'আজ পোস্ট হওয়ার কথা ছিল';
    }

    /**
     * Run every minute: post each menu whose time has come today. A menu is
     * not posted more than two hours late (the scheduler was down), on a day
     * off, or when the cook already posted that meal herself.
     *
     * @return Collection<int, Post>
     */
    public function runDailyMenus(?Carbon $now = null): Collection
    {
        $now = ($now ?? now())->copy()->timezone(DhakaTime::TZ);
        $hm = $now->format('H:i');
        $late = $now->copy()->subHours(2)->format('H:i');
        $posts = collect();

        KitchenMenu::where('active', true)->with('kitchen')->get()
            ->filter(fn (KitchenMenu $m) => in_array($now->dayOfWeek, array_map('intval', $m->weekdays), true)
                && $m->postAt() <= $hm && $m->postAt() > $late)
            ->each(function (KitchenMenu $menu) use ($posts) {
                if (self::postedToday($menu->kitchen, $menu->meal_slot) || self::offToday($menu->kitchen_id, $menu->meal_slot)) {
                    return;
                }
                try {
                    $posts->push($this->post($menu->kitchen, $menu->postData(), [], 'auto', null));
                } catch (Throwable $e) {
                    // Do not try again every minute: mark the day and tell the cook.
                    KitchenDayOff::firstOrCreate(['kitchen_id' => $menu->kitchen_id, 'date' => BookingPlanner::today(), 'meal_slot' => $menu->meal_slot->value]);
                    $why = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : 'একটা সমস্যা হয়েছে';
                    Push::toUser($menu->kitchen->user_id, 'আজকের মেনু পোস্ট হয়নি', "{$menu->meal_slot->label()}: {$why} অ্যাপ থেকে নিজে পোস্ট দিন।", '/cook/new', 'menu-'.$menu->id);
                    Log::warning('daily menu not posted', ['menu' => $menu->id, 'error' => $e->getMessage()]);
                }
            });

        return $posts;
    }
}
