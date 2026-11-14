<?php

namespace App\Services\Regular;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenDayOff;
use App\Models\KitchenMenu;
use App\Models\MealChoice;
use App\Models\MenuNotice;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Push\Push;
use App\Support\DhakaTime;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F61: "আজ কী রান্না" and the regular customer's answer.
 *
 * The cook (or a shahidar/admin for her, or her daily menu at 8 am) says what
 * she is cooking for a meal today. Her regular customers get a push and can say
 * "আজ খাব না" or "এই খাবার খাব না — অন্য কিছু চাই". Either way no box is booked
 * from her for that meal. The cook is never told; for "অন্য কিছু চাই" the
 * customer's shahidar and the admins are alerted and arrange other food.
 * No answer = booked as usual.
 */
class TodayMenu
{
    public const DAILY_MENU_AT = '08:00';

    public function __construct(private readonly BookingPlanner $planner) {}

    /** Says what is cooking for a meal today and tells the regular customers. */
    public function announce(Kitchen $kitchen, MealSlot $slot, string $title, string $source, ?User $by = null): MenuNotice
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if (mb_strlen($title) < 2) {
            throw ValidationException::withMessages(['title' => 'কী রান্না হবে লিখুন।']);
        }
        $today = BookingPlanner::today();
        $existing = MenuNotice::where('kitchen_id', $kitchen->id)->whereDate('service_date', $today)->where('meal_slot', $slot->value)->first();
        if ($existing && $existing->title === $title) {
            return $existing;
        }

        $notice = MenuNotice::updateOrCreate(
            ['kitchen_id' => $kitchen->id, 'service_date' => $today, 'meal_slot' => $slot->value],
            ['title' => mb_substr($title, 0, 150), 'source' => $source, 'posted_by' => $by?->id],
        );

        foreach ($this->customersFor($kitchen, $slot) as $pairing) {
            Push::toUser(
                $pairing->customer->user_id,
                "আজ {$slot->label()}: {$title}",
                $existing ? 'আজকের রান্না বদলেছে। খাবেন? না খেলে অ্যাপে জানান।' : 'খাবেন? না খেলে অ্যাপে জানান — অন্য খাবারের ব্যবস্থা হবে।',
                '/regular',
                "menu-{$kitchen->id}-{$slot->value}",
            );
        }

        return $notice;
    }

    /** Today's notices of a kitchen, by meal. @return Collection<string, MenuNotice> */
    public static function noticesToday(Kitchen $kitchen): Collection
    {
        return MenuNotice::where('kitchen_id', $kitchen->id)->whereDate('service_date', BookingPlanner::today())->get()->keyBy(fn ($n) => $n->meal_slot->value);
    }

    /**
     * The customer's answer: 'eat' (undo), 'skip' or 'change' with what they want.
     */
    public function choose(Customer $customer, MealSlot $slot, string $choice, ?string $wish, User $by): ?MealChoice
    {
        $today = BookingPlanner::today();
        $pairing = $customer->pairing()->with(['kitchen', 'shahidar'])->first();
        if (! $pairing || $customer->status !== CustomerStatus::Active || ! in_array($slot->value, $customer->meal_slots ?? [], true)) {
            throw ValidationException::withMessages(['meal_slot' => 'আজ এই বেলায় আপনার বুকিং নেই।']);
        }
        if ($choice === 'change' && mb_strlen(trim((string) $wish)) < 2) {
            throw ValidationException::withMessages(['wish' => 'কী খেতে চান লিখুন, যেমন "মাছ ছাড়া কিছু" বা "শুধু ডাল-ভাত"।']);
        }

        $result = DB::transaction(function () use ($customer, $slot, $choice, $wish, $today, $pairing) {
            $order = Order::where('customer_id', $customer->id)->whereDate('service_date', $today)
                ->where('meal_slot', $slot->value)->latest('id')->lockForUpdate()->first();
            $existing = MealChoice::where('customer_id', $customer->id)->whereDate('service_date', $today)->where('meal_slot', $slot->value)->first();

            if ($choice === 'eat') {
                $existing?->delete();

                return null;
            }

            if ($order && ! in_array($order->status, [OrderStatus::Booked, OrderStatus::Asking, OrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['meal_slot' => 'খাবার আগেই রওনা হয়ে গেছে। শাহিদারকে ফোন করুন।']);
            }
            if ($order && $order->status !== OrderStatus::Cancelled) {
                $this->planner->cancel($order, 'customer', $choice === 'skip' ? 'গ্রাহক: আজ খাবেন না' : 'গ্রাহক: এই খাবার খাবেন না');
            }

            $notice = MenuNotice::where('kitchen_id', $pairing->kitchen_id)->whereDate('service_date', $today)->where('meal_slot', $slot->value)->first();

            return MealChoice::updateOrCreate(
                ['customer_id' => $customer->id, 'service_date' => $today, 'meal_slot' => $slot->value],
                [
                    'choice' => $choice,
                    'wish' => $choice === 'change' ? mb_substr(trim((string) $wish), 0, 300) : null,
                    'menu_title' => $notice?->title ?? $order?->title,
                    'kitchen_id' => $pairing->kitchen_id,
                    'shahidar_id' => $pairing->shahidar_id,
                    'status' => 'open',
                    'handled_by' => null,
                    'handled_at' => null,
                    'handled_note' => null,
                ],
            );
        });

        if ($choice === 'eat') {
            // Back on: book now if the cook has already posted.
            $this->planner->bookLate($customer);
        } elseif ($result && $result->choice === 'change' && ($result->wasRecentlyCreated || $result->wasChanged(['choice', 'wish', 'status']))) {
            $this->alert($result->loadMissing('customer.user'));
        }

        return $result;
    }

    /** The shahidar and every admin hear about "অন্য কিছু চাই" at once. */
    private function alert(MealChoice $c): void
    {
        $name = $c->customer->user->name ?: $c->customer->user->phone;
        $title = "{$name} আজ {$c->meal_slot->label()}-এর খাবার খাবেন না";
        $body = ($c->menu_title ? "রান্না: {$c->menu_title}। " : '')."চান: {$c->wish}। ফোন করে অন্য খাবারের ব্যবস্থা করুন।";

        Push::toUser($c->shahidar_id, $title, $body, '/shahidar', 'meal-choice-'.$c->id);
        User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser($id, $title, $body, '/admin', 'meal-choice-'.$c->id));
    }

    /** "ব্যবস্থা হয়েছে": the shahidar or an admin sorted it out. */
    public function handle(MealChoice $choice, User $by, ?string $note): MealChoice
    {
        abort_unless($choice->choice === 'change', 422);
        $choice->forceFill([
            'status' => 'handled',
            'handled_by' => $by->id,
            'handled_at' => now(),
            'handled_note' => filled($note) ? mb_substr(trim($note), 0, 300) : null,
        ])->save();

        if ($by->id !== $choice->shahidar_id) {
            Push::toUser($choice->shahidar_id, 'ব্যবস্থা হয়ে গেছে', ($choice->customer->user->name ?? '').': '.($note ?: 'দাওয়াত টিম ব্যবস্থা করেছে।'), '/shahidar', 'meal-choice-'.$choice->id);
        }
        if ($by->hasRole(Roles::SHAHIDAR) && ! $by->hasRole(Roles::ADMIN)) {
            User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser($id, 'শাহিদার ব্যবস্থা করেছেন', ($choice->customer->user->name ?? '').': '.($note ?: 'ব্যবস্থা হয়েছে।'), '/admin', 'meal-choice-'.$choice->id));
        }

        return $choice;
    }

    /** Today's "অন্য কিছু চাই" requests, open first. */
    public static function requestsToday(?User $shahidar = null): Collection
    {
        return MealChoice::where('choice', 'change')->whereDate('service_date', BookingPlanner::today())
            ->when($shahidar, fn ($query) => $query->where('shahidar_id', $shahidar->id))
            ->with(['customer.user', 'customer.area', 'kitchen', 'handler'])
            ->orderByRaw("status = 'handled'")->latest('id')->get();
    }

    /** 8 am: cooks with a daily menu for today announce it, unless they already did or are off. */
    public function runDailyMenus(?Carbon $now = null): int
    {
        $now = ($now ?? now())->copy()->timezone(DhakaTime::TZ);
        if ($now->format('H:i') < self::DAILY_MENU_AT) {
            return 0;
        }
        $n = 0;
        KitchenMenu::where('active', true)->with('kitchen')->get()
            ->filter(fn (KitchenMenu $m) => in_array($now->dayOfWeek, array_map('intval', $m->weekdays), true) && $m->kitchen?->isApproved())
            ->each(function (KitchenMenu $m) use (&$n) {
                $slot = $m->meal_slot;
                $already = MenuNotice::where('kitchen_id', $m->kitchen_id)->whereDate('service_date', BookingPlanner::today())->where('meal_slot', $slot->value)->exists();
                $off = KitchenDayOff::where('kitchen_id', $m->kitchen_id)->whereDate('date', BookingPlanner::today())->where('meal_slot', $slot->value)->exists();
                if (! $already && ! $off && $this->customersFor($m->kitchen, $slot)->isNotEmpty()) {
                    $this->announce($m->kitchen, $slot, $m->title, 'daily_menu');
                    $n++;
                }
            });

        return $n;
    }

    /** Regular customers of this cook who eat this meal today (not paused). */
    private function customersFor(Kitchen $kitchen, MealSlot $slot): Collection
    {
        $weekday = now(DhakaTime::TZ)->dayOfWeek;

        return Pairing::open()->where('kitchen_id', $kitchen->id)
            ->whereHas('customer', fn ($query) => $query->where('status', CustomerStatus::Active))
            ->with('customer')->get()
            ->filter(fn (Pairing $p) => in_array($slot->value, $p->customer->meal_slots ?? [], true)
                && in_array($weekday, array_map('intval', $p->customer->weekdays ?? []), true));
    }

    /**
     * For the customer's app: each meal today, what is cooking and their answer.
     *
     * @return list<array>
     */
    public function forCustomer(Customer $customer): array
    {
        $pairing = $customer->pairing()->first();
        if (! $pairing || $customer->status !== CustomerStatus::Active) {
            return [];
        }
        $today = BookingPlanner::today();
        $weekday = now(DhakaTime::TZ)->dayOfWeek;
        if (! in_array($weekday, array_map('intval', $customer->weekdays ?? []), true)) {
            return [];
        }
        $notices = MenuNotice::where('kitchen_id', $pairing->kitchen_id)->whereDate('service_date', $today)->get()->keyBy(fn ($n) => $n->meal_slot->value);
        $choices = MealChoice::where('customer_id', $customer->id)->whereDate('service_date', $today)->get()->keyBy(fn ($c) => $c->meal_slot->value);
        $orders = Order::where('customer_id', $customer->id)->whereDate('service_date', $today)->get()->keyBy(fn ($o) => $o->meal_slot->value);

        $rows = [];
        foreach (MealSlot::cases() as $slot) {
            if (! in_array($slot->value, $customer->meal_slots ?? [], true)) {
                continue;
            }
            $order = $orders->get($slot->value);
            $choice = $choices->get($slot->value);
            $title = $notices->get($slot->value)?->title ?? ($order?->post_id ? $order->title : null);
            $rows[] = [
                'slot' => $slot->value,
                'label' => $slot->label(),
                'title' => $title,
                'choice' => $choice?->choice ?? 'eat',
                'wish' => $choice?->wish,
                'handled' => $choice?->status === 'handled',
                'handled_note' => $choice?->status === 'handled' ? $choice->handled_note : null,
                // Can still answer until the box leaves the cook.
                'can_choose' => ! $order || in_array($order->status, [OrderStatus::Booked, OrderStatus::Asking, OrderStatus::Cancelled], true),
                // A box already cancelled cannot come back; "খাব" only undoes an answer given before booking.
                'can_undo' => $choice !== null && ! ($order && $order->status === OrderStatus::Cancelled),
            ];
        }

        return $rows;
    }
}
