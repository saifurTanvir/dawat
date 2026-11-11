<?php

namespace App\Services\Orders;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Jobs\SendOrderSms;
use App\Models\Customer;
use App\Models\CustomerPause;
use App\Models\Kitchen;
use App\Models\KitchenDayOff;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Services\Otp\OtpPurpose;
use App\Services\Push\Push;
use App\Support\DhakaTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F26: turns standing bookings into today's orders. A cook's post first fills
 * her paired customers' plates; only what is left goes to the public feed.
 */
class BookingPlanner
{
    /** Today's date in Dhaka, Y-m-d. */
    public static function today(): string
    {
        return now(DhakaTime::TZ)->toDateString();
    }

    /**
     * Pairings that should get a box from this cook for this meal today and
     * do not have one yet (booked, cancelled or otherwise).
     *
     * @return Collection<int, Pairing>
     */
    public function expected(Kitchen $kitchen, MealSlot $slot, ?string $date = null): Collection
    {
        $date ??= self::today();
        $weekday = Carbon::parse($date, DhakaTime::TZ)->dayOfWeek;

        return Pairing::open()
            ->where('kitchen_id', $kitchen->id)
            ->whereHas('customer', fn ($q) => $q->where('status', CustomerStatus::Active))
            ->with(['customer.user', 'customer.area'])
            ->get()
            ->filter(fn (Pairing $p) => in_array($slot->value, $p->customer->meal_slots, true)
                && in_array($weekday, array_map('intval', $p->customer->weekdays), true))
            ->reject(fn (Pairing $p) => CustomerPause::where('customer_id', $p->customer_id)->covering($date)->exists())
            ->reject(fn (Pairing $p) => Order::where('customer_id', $p->customer_id)
                ->whereDate('service_date', $date)->where('meal_slot', $slot->value)->exists())
            ->values();
    }

    public function bookedPlates(Kitchen $kitchen, MealSlot $slot): int
    {
        return (int) $this->expected($kitchen, $slot)->sum(fn (Pairing $p) => $p->customer->plates);
    }

    /**
     * Inside PublishPost's transaction: books today's boxes on this post and
     * leaves the rest for the feed. Refuses a post with too few plates.
     *
     * @return Collection<int, Order>
     */
    public function bookForPost(Post $post): Collection
    {
        $pairings = $this->expected($post->kitchen, $post->meal_slot);
        $needed = (int) $pairings->sum(fn (Pairing $p) => $p->customer->plates);

        if ($needed > $post->plates_total) {
            throw ValidationException::withMessages([
                'plates_total' => 'আজ নিয়মিত গ্রাহকদের বুকিং '.Order::banglaDigits($needed).' প্লেট। অন্তত এতগুলো প্লেট দিন।',
            ]);
        }

        $orders = $pairings->map(fn (Pairing $p) => $this->bookOne($p, $post));

        if ($needed > 0) {
            $post->plates_left = $post->plates_total - $needed;
            if ($post->plates_left === 0) {
                $post->status = PostStatus::SoldOut;
            }
            $post->save();
        }

        return $orders;
    }

    /** One customer's box from a post: booked, or held while they are asked about a higher price (F30). */
    private function bookOne(Pairing $p, Post $post): Order
    {
        $limit = $p->customer->max_price_paisa;
        $ask = $limit !== null && $post->price_paisa > $limit;

        return $this->createOrder($p, $post->meal_slot, $ask ? OrderStatus::Asking : OrderStatus::Booked, [
            'post_id' => $post->id,
            'title' => $post->title,
            'food_paisa' => $post->price_paisa * $p->customer->plates,
            'ask_until' => $ask ? now()->addMinutes((int) config('dawat.orders.ask_minutes', 45)) : null,
        ]);
    }

    /** Today's live post of this cook for this meal that still has plates on the feed. */
    private function livePost(Kitchen $kitchen, MealSlot $slot, bool $lock = false): ?Post
    {
        return Post::where('kitchen_id', $kitchen->id)->where('meal_slot', $slot->value)
            ->where('status', PostStatus::Live)->where('available_until', '>', now())
            ->where('published_at', '>=', now(DhakaTime::TZ)->startOfDay())
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->latest('id')->first();
    }

    /**
     * F45: a customer paired (or whose booking changed) after the cook posted
     * today gets a box from that post at once, if enough plates are left.
     *
     * @return Collection<int, Order>
     */
    public function bookLate(Customer $customer): Collection
    {
        $customer->refresh();
        $pairing = $customer->pairing()->with(['customer.user', 'customer.area', 'kitchen'])->first();
        if (! $pairing || $customer->status !== CustomerStatus::Active) {
            return collect();
        }

        $made = collect();
        foreach (MealSlot::cases() as $slot) {
            $order = DB::transaction(function () use ($pairing, $slot) {
                if (! $this->expected($pairing->kitchen, $slot)->contains('id', $pairing->id)) {
                    return null;
                }
                $post = $this->livePost($pairing->kitchen, $slot, lock: true);
                if (! $post || $post->plates_left < $pairing->customer->plates) {
                    return null;
                }
                $order = $this->bookOne($pairing, $post);
                $post->plates_left -= $pairing->customer->plates;
                if ($post->plates_left === 0) {
                    $post->status = PostStatus::SoldOut;
                }
                $post->save();

                return $order;
            });
            if ($order) {
                // The post's summary (F44) went out before this customer joined: tell the shahidar now.
                Push::toUser($pairing->shahidar_id, "নতুন বক্স {$order->code()} · {$slot->label()}",
                    "{$pairing->kitchen->display_name} থেকে {$pairing->customer->user->name}-এর জন্য (পোস্টের পরে জুটি)।", '/shahidar', "order-{$order->id}");
                $made->push($order);
            }
        }

        return $made;
    }

    /**
     * F45: for each meal of the customer's booking, what happens today and,
     * when there is no box, why.
     *
     * @return list<array{slot: string, label: string, line: string, ok: bool, can_book: bool}>
     */
    public function todayReasons(Customer $customer): array
    {
        $today = self::today();
        $weekday = Carbon::parse($today, DhakaTime::TZ)->dayOfWeek;
        $pairing = $customer->pairing()->with('kitchen')->first();
        $rows = [];

        foreach (MealSlot::cases() as $slot) {
            if (! in_array($slot->value, $customer->meal_slots ?? [], true)) {
                continue;
            }
            $order = Order::where('customer_id', $customer->id)->whereDate('service_date', $today)->where('meal_slot', $slot->value)->latest('id')->first();
            $post = $pairing ? $this->livePost($pairing->kitchen, $slot) : null;
            $canBook = false;

            $line = match (true) {
                $order !== null => 'বক্স '.$order->code().' · '.($order->cancelled_by === 'cook' ? 'আজ রান্না নেই' : $order->status->label())
                    .($order->status === OrderStatus::Cancelled && $order->cancel_reason ? ' ('.$order->cancel_reason.')' : ''),
                $customer->status !== CustomerStatus::Active => 'গ্রাহক এখন "'.$customer->status->getLabel().'" — জুটি চালু নেই',
                $pairing === null => 'জুটি বাঁধা হয়নি',
                ! in_array($weekday, array_map('intval', $customer->weekdays ?? []), true) => 'আজ বুকিংয়ের দিন না',
                CustomerPause::where('customer_id', $customer->id)->covering($today)->exists() => 'আজ বিরতিতে আছেন',
                KitchenDayOff::where('kitchen_id', $pairing->kitchen_id)->whereDate('date', $today)->where('meal_slot', $slot->value)->exists() => 'গৃহিণী আজ এই বেলায় রাঁধছেন না',
                $post !== null && $post->plates_left < $customer->plates => 'পোস্ট এসেছে, কিন্তু ফিডে যথেষ্ট প্লেট বাকি নেই ('.Order::banglaDigits((string) $post->plates_left).' বাকি)',
                $post !== null => (function () use (&$canBook) {
                    $canBook = true;

                    return 'পোস্ট এসেছে, এখনো বক্স হয়নি — এখন বুক করা যায়';
                })(),
                default => 'গৃহিণী এখনো পোস্ট দেননি',
            };

            $rows[] = ['slot' => $slot->value, 'label' => $slot->label(), 'line' => $line, 'ok' => $order !== null && $order->status !== OrderStatus::Cancelled, 'can_book' => $canBook];
        }

        return $rows;
    }

    /**
     * The cook is not cooking this meal today: record it and tell the customers.
     *
     * @return Collection<int, Order>
     */
    public function cookOff(Kitchen $kitchen, MealSlot $slot): Collection
    {
        // F43: also stops today's daily-menu post for this meal.
        KitchenDayOff::firstOrCreate(['kitchen_id' => $kitchen->id, 'date' => self::today(), 'meal_slot' => $slot->value]);

        return DB::transaction(fn () => $this->expected($kitchen, $slot)->map(
            fn (Pairing $p) => $this->createOrder($p, $slot, OrderStatus::Cancelled, [
                'cancelled_by' => 'cook',
                'cancel_reason' => 'আজ রান্না নেই',
            ])
        ));
    }

    /** F30: the customer (or their shahidar) says yes to today's higher price. */
    public function accept(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== OrderStatus::Asking) {
                throw ValidationException::withMessages(['order' => 'এই বক্সের উত্তর আগেই দেওয়া হয়েছে বা সময় শেষ।']);
            }
            $code = (string) random_int(1000, 9999);
            $order->forceFill([
                'status' => OrderStatus::Booked,
                'ask_until' => null,
                'delivery_code_hash' => Hash::make($code),
                'delivery_fee_paisa' => (int) $order->customer->area->delivery_fee_paisa,
            ])->save();

            // F37: always queued: a free push goes out; the SMS only when ORDER_SMS_ENABLED.
            SendOrderSms::dispatch($order->id, OtpPurpose::OrderBooked, $code)->afterCommit();

            return $order;
        });
    }

    /** F30: nobody answered in time; the plates go back to the feed. */
    public function expireAsks(): int
    {
        $count = 0;
        Order::where('status', OrderStatus::Asking)->where('ask_until', '<=', now())->get()
            ->each(function (Order $o) use (&$count) {
                $this->cancel($o, 'system', 'দামের উত্তর আসেনি');
                $count++;
            });

        return $count;
    }

    /** "আজ না": before pickup the plates go back to the public feed. */
    public function cancel(Order $order, string $by, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $by, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, [OrderStatus::Booked, OrderStatus::Asking], true)) {
                throw ValidationException::withMessages(['order' => 'খাবার পিকআপ হয়ে গেছে বা আগেই বাতিল, এখন আর বাদ দেওয়া যাবে না।']);
            }

            $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_by' => $by, 'cancel_reason' => $reason, 'ask_until' => null])->save();

            $post = $order->post_id ? Post::whereKey($order->post_id)->lockForUpdate()->first() : null;
            if ($post && in_array($post->status, [PostStatus::Live, PostStatus::SoldOut], true) && $post->available_until->isFuture()) {
                $post->plates_left = min($post->plates_total, $post->plates_left + $order->plates);
                $post->status = PostStatus::Live;
                $post->save();
            }

            return $order;
        });
    }

    /** Adds days off; a not-yet-picked box inside them is cancelled. */
    public function pause(Customer $customer, string $from, string $to, ?int $by = null, ?string $reason = null): CustomerPause
    {
        $today = self::today();
        $max = (int) config('dawat.orders.max_pause_days', 60);
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to), false);
        if ($from < $today || $days < 0 || $days > $max) {
            throw ValidationException::withMessages(['starts_on' => 'আজ বা পরের তারিখ দিন, সর্বোচ্চ '.Order::banglaDigits($max).' দিন।']);
        }

        return DB::transaction(function () use ($customer, $from, $to, $by, $reason) {
            $pause = $customer->pauses()->create(['starts_on' => $from, 'ends_on' => $to, 'reason' => $reason, 'created_by' => $by]);

            $customer->orders()->whereIn('status', [OrderStatus::Booked, OrderStatus::Asking])
                ->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to)
                ->get()->each(fn (Order $o) => $this->cancel($o, 'customer', 'বিরতি'));

            return $pause;
        });
    }

    private function createOrder(Pairing $pairing, MealSlot $slot, OrderStatus $status, array $extra): Order
    {
        $date = self::today();
        $code = $status === OrderStatus::Booked ? (string) random_int(1000, 9999) : '';

        for ($attempt = 0; ; $attempt++) {
            try {
                $order = Order::create([
                    'service_date' => $date,
                    'seq' => (int) Order::whereDate('service_date', $date)->lockForUpdate()->max('seq') + 1,
                    'meal_slot' => $slot,
                    'kitchen_id' => $pairing->kitchen_id,
                    'customer_id' => $pairing->customer_id,
                    'shahidar_id' => $pairing->shahidar_id,
                    'pairing_id' => $pairing->id,
                    'plates' => $pairing->customer->plates,
                    'delivery_fee_paisa' => $status === OrderStatus::Booked ? (int) $pairing->customer->area->delivery_fee_paisa : 0,
                    'status' => $status,
                    'delivery_code_hash' => $code !== '' ? Hash::make($code) : null,
                    ...$extra,
                ]);
                break;
            } catch (QueryException $e) {
                // Two cooks posting at the same moment can race for a number.
                if ($attempt >= 4 || ! str_contains($e->getMessage(), 'orders_service_date_seq_unique')) {
                    throw $e;
                }
            }
        }

        // F37: always queued: a free push goes out; the SMS only when ORDER_SMS_ENABLED.
        $purpose = match ($status) {
            OrderStatus::Booked => OtpPurpose::OrderBooked,
            OrderStatus::Asking => OtpPurpose::PriceAsk,
            default => OtpPurpose::CookOff,
        };
        SendOrderSms::dispatch($order->id, $purpose, $code)->afterCommit();

        return $order;
    }
}
