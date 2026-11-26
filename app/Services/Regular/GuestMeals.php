<?php

namespace App\Services\Regular;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Jobs\SendOrderSms;
use App\Models\Customer;
use App\Models\GuestMeal;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Otp\OtpPurpose;
use App\Services\Push\Push;
use App\Support\DhakaTime;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F64: guest meals for regular customers.
 *
 * The customer asks (today or tomorrow, a meal, plates). Their cook and their
 * shahidar get a push; admins see it. The cook answers "পারব"/"পারব না" (she
 * never sees who asked). No answer in 5 minutes → the shahidar is told to call
 * her. If she cannot, the shahidar arranges it from another cook's live post, or
 * some other way, or says it cannot be done. The customer hears the outcome.
 *
 * A guest box is its own one-off order (cash on delivery), delivered by the
 * customer's shahidar; from her own cook it rides with the regular box (no
 * delivery fee), from another cook the area's fee applies.
 */
class GuestMeals
{
    public const FOLLOW_UP_MINUTES = 5;

    public const MAX_PLATES = 10;

    public function request(Customer $customer, string $day, MealSlot $slot, int $plates, ?string $note): GuestMeal
    {
        $pairing = $customer->pairing()->with(['kitchen', 'shahidar'])->first();
        if (! $pairing || $customer->status !== CustomerStatus::Active) {
            throw ValidationException::withMessages(['meal_slot' => 'নিয়মিত খাবার চালু থাকলে তবেই অতিথির খাবার চাওয়া যায়।']);
        }
        $date = $day === 'tomorrow' ? now(DhakaTime::TZ)->addDay()->toDateString() : BookingPlanner::today();
        if ($plates < 1 || $plates > self::MAX_PLATES) {
            throw ValidationException::withMessages(['plates' => 'অতিথির জন্য ১ থেকে ১০ প্লেট।']);
        }

        $guest = GuestMeal::where('customer_id', $customer->id)->whereDate('service_date', $date)
            ->where('meal_slot', $slot->value)->whereIn('status', GuestMeal::OPEN)->first();
        if ($guest) {
            $guest->update(['plates' => $plates, 'note' => self::clean($note), 'status' => 'asked', 'cook_replied_at' => null, 'followup_at' => null]);
        } else {
            $guest = GuestMeal::create([
                'customer_id' => $customer->id,
                'kitchen_id' => $pairing->kitchen_id,
                'shahidar_id' => $pairing->shahidar_id,
                'service_date' => $date,
                'meal_slot' => $slot->value,
                'plates' => $plates,
                'note' => self::clean($note),
                'status' => 'asked',
            ]);
        }

        $line = "{$guest->when()}: অতিথির জন্য ".Order::banglaDigits($plates).' প্লেট বাড়তি'.($guest->note ? " — {$guest->note}" : '');
        Push::toUser($pairing->kitchen->user_id, 'অতিথির খাবারের অনুরোধ', "{$line}। পারবেন? অ্যাপে জানান।", '/cook', 'guest-'.$guest->id);
        Push::toUser($pairing->shahidar_id, 'অতিথির খাবারের অনুরোধ', ($customer->user->name ?: $customer->user->phone)." · {$line} · গৃহিণী {$pairing->kitchen->display_name}", '/shahidar', 'guest-'.$guest->id);

        return $guest;
    }

    /** The cook (or her shahidar/admin after a call) answers. */
    public function cookReply(GuestMeal $guest, bool $yes, User $by): GuestMeal
    {
        if (! $guest->isOpen()) {
            throw ValidationException::withMessages(['guest' => 'এই অনুরোধের ব্যবস্থা আগেই হয়ে গেছে।']);
        }
        $guest->update(['status' => $yes ? 'cook_yes' : 'cook_no', 'cook_replied_at' => now(), 'handled_by' => $by->id]);

        if ($yes) {
            // Already cooking this meal today with plates to spare: book it now.
            $post = $this->livePostOf($guest);
            $today = $this->todaysPostOf($guest);
            if ($post) {
                $this->arrangeFromPost($guest, $post, $by);
            } elseif ($today) {
                // She already posted and the plates are gone: she said she can cook the extra — book it at that price.
                $this->arrangeFromPost($guest, $today, $by, extra: true);
            } else {
                Push::toUser($guest->shahidar_id, 'গৃহিণী অতিথির খাবার দেবেন', "{$guest->customer->user->name}: {$guest->when()}, ".Order::banglaDigits($guest->plates).' প্লেট। পোস্টের সাথে বুক হবে।', '/shahidar', 'guest-'.$guest->id);
            }
        } else {
            Push::toUser($guest->shahidar_id, 'গৃহিণী অতিথির খাবার দিতে পারবেন না', "{$guest->customer->user->name}: {$guest->when()}, ".Order::banglaDigits($guest->plates).' প্লেট। অন্য পোস্ট থেকে বা অন্যভাবে ব্যবস্থা করুন।', '/shahidar', 'guest-'.$guest->id);
        }

        return $guest->fresh();
    }

    /** Called when a cook posts: book her accepted guest meals for that meal. */
    public function fulfilForPost(Post $post): void
    {
        GuestMeal::where('kitchen_id', $post->kitchen_id)->where('status', 'cook_yes')
            ->whereDate('service_date', BookingPlanner::today())->where('meal_slot', $post->meal_slot->value)
            ->get()->each(function (GuestMeal $guest) use ($post) {
                $post->refresh();
                if ($post->plates_left >= $guest->plates) {
                    try {
                        $this->arrangeFromPost($guest, $post, null);

                        return;
                    } catch (ValidationException) {
                        // fall through: the shahidar sorts it out
                    }
                }

                // Not enough plates left in the post: she agreed to the guest plates, so they are booked as extra.
                try {
                    $this->arrangeFromPost($guest, $post, null, extra: true);
                } catch (ValidationException) {
                    Push::toUser($guest->shahidar_id, 'অতিথির খাবারের বক্স হয়নি',
                        "{$guest->customer->user->name}: গৃহিণী ও গ্রাহককে ফোন করে ব্যবস্থা করুন।", '/shahidar', 'guest-'.$guest->id);
                }

            });
    }

    /**
     * Books the guest plates from a live post (her cook's or anyone's) and tells the customer.
     * With $extra (her own cook said "পারব" after the post ran out), the plates are cooked on
     * top of the post: booked at its price without taking from it.
     */
    public function arrangeFromPost(GuestMeal $guest, Post $post, ?User $by, bool $extra = false): Order
    {
        return DB::transaction(function () use ($guest, $post, $by, $extra) {
            $guest = GuestMeal::whereKey($guest->id)->lockForUpdate()->firstOrFail();
            $post = Post::whereKey($post->id)->lockForUpdate()->with('kitchen.area')->firstOrFail();
            $customer = $guest->customer()->with(['user', 'area'])->first();

            if (! $guest->isOpen()) {
                throw ValidationException::withMessages(['guest' => 'এই অনুরোধের ব্যবস্থা আগেই হয়ে গেছে।']);
            }
            if ($guest->service_date->toDateString() !== BookingPlanner::today()) {
                throw ValidationException::withMessages(['guest' => 'এই অনুরোধ আজকের নয়; সেদিন ব্যবস্থা করুন।']);
            }
            if ($extra) {
                if ($post->kitchen_id !== $guest->kitchen_id) {
                    throw ValidationException::withMessages(['post_id' => 'বাড়তি প্লেট শুধু নিজের গৃহিণীর।']);
                }
            } elseif (! Post::whereKey($post->id)->visibleNow()->exists() || $post->plates_left < $guest->plates) {
                throw ValidationException::withMessages(['post_id' => 'এই পোস্টে এত প্লেট বাকি নেই।']);
            } else {
                $post->plates_left -= $guest->plates;
                if ($post->plates_left === 0) {
                    $post->status = PostStatus::SoldOut;
                }
                $post->save();
            }

            $ownCook = $post->kitchen_id === $guest->kitchen_id;
            $code = (string) random_int(1000, 9999);
            $date = BookingPlanner::today();
            for ($attempt = 0; ; $attempt++) {
                try {
                    $order = Order::create([
                        'kind' => 'oneoff',
                        'service_date' => $date,
                        'seq' => (int) Order::whereDate('service_date', $date)->lockForUpdate()->max('seq') + 1,
                        'meal_slot' => $post->meal_slot,
                        'post_id' => $post->id,
                        'kitchen_id' => $post->kitchen_id,
                        'buyer_id' => $customer->user_id,
                        'shahidar_id' => $guest->shahidar_id,
                        'guest_meal_id' => $guest->id,
                        'receive' => 'delivery',
                        'delivery_address' => $customer->address.', '.$customer->area?->name,
                        'buyer_note' => 'অতিথির খাবার'.($guest->note ? ' — '.$guest->note : ''),
                        'title' => $post->title,
                        'plates' => $guest->plates,
                        'food_paisa' => $post->price_paisa * $guest->plates,
                        // From her own cook it travels with the regular box.
                        'delivery_fee_paisa' => $ownCook ? 0 : (int) ($customer->area?->delivery_fee_paisa ?? 0),
                        'status' => OrderStatus::Booked,
                        'delivery_code_hash' => Hash::make($code),
                        'payment_method' => 'cash',
                    ]);
                    break;
                } catch (QueryException $e) {
                    if ($attempt >= 4 || ! str_contains($e->getMessage(), 'orders_service_date_seq_unique')) {
                        throw $e;
                    }
                }
            }

            $guest->update(['status' => 'arranged', 'order_id' => $order->id, 'handled_by' => $by?->id ?? $guest->handled_by]);

            SendOrderSms::dispatch($order->id, OtpPurpose::OrderBooked, $code)->afterCommit();
            SendOrderSms::dispatch($order->id, OtpPurpose::CookNewOrder, '', 'cook')->afterCommit();
            Push::toUser($guest->shahidar_id, 'অতিথির বক্স বুক হয়েছে', "{$customer->user->name}: বক্স {$order->code()} · {$post->kitchen->display_name} থেকে।", '/shahidar', 'guest-'.$guest->id);

            return $order;
        });
    }

    /** Sorted out some other way (or, with $cancel, it cannot be done). */
    public function close(GuestMeal $guest, User $by, ?string $note, bool $cancel = false): GuestMeal
    {
        if (! $guest->isOpen()) {
            throw ValidationException::withMessages(['guest' => 'এই অনুরোধের ব্যবস্থা আগেই হয়ে গেছে।']);
        }
        $guest->update(['status' => $cancel ? 'cancelled' : 'arranged', 'handled_by' => $by->id, 'handled_note' => self::clean($note)]);
        Push::toUser(
            $guest->customer->user_id,
            $cancel ? 'অতিথির খাবারের ব্যবস্থা হয়নি' : 'অতিথির খাবারের ব্যবস্থা হয়েছে',
            "{$guest->when()}, ".Order::banglaDigits($guest->plates).' প্লেট'.($guest->handled_note ? " — {$guest->handled_note}" : ($cancel ? ' — দুঃখিত, এবার পারা গেল না।' : '')),
            '/regular',
            'guest-'.$guest->id,
        );

        return $guest;
    }

    /** The customer takes the request back before it is arranged. */
    public function cancelByCustomer(GuestMeal $guest): void
    {
        if (! $guest->isOpen()) {
            throw ValidationException::withMessages(['guest' => 'ব্যবস্থা হয়ে গেছে; বাতিল করতে এলাকা ম্যানেজারকে ফোন করুন।']);
        }
        $before = $guest->status;
        $guest->update(['status' => 'cancelled', 'handled_note' => 'গ্রাহক নিজে বাতিল করেছেন']);
        Push::toUser($guest->shahidar_id, 'অতিথির খাবার লাগবে না', "{$guest->customer->user->name}: {$guest->when()}।", '/shahidar', 'guest-'.$guest->id);
        if ($before !== 'cook_no') {
            Push::toUser($guest->kitchen?->user_id, 'অতিথির খাবার লাগবে না', "{$guest->when()}-এর ".Order::banglaDigits($guest->plates).' প্লেট বাড়তি লাগবে না।', '/cook', 'guest-'.$guest->id);
        }
    }

    /** Every minute: the cook has not answered in 5 minutes → tell the shahidar (and admins). */
    public function followUps(): int
    {
        $late = GuestMeal::where('status', 'asked')->whereNull('followup_at')
            ->where('created_at', '<=', now()->subMinutes(self::FOLLOW_UP_MINUTES))->with(['customer.user', 'kitchen.user'])->get();

        foreach ($late as $guest) {
            $guest->update(['followup_at' => now()]);
            $body = "{$guest->customer->user->name}: {$guest->when()}, ".Order::banglaDigits($guest->plates).' প্লেট। গৃহিণী '
                .($guest->kitchen?->display_name ?? '').' ('.($guest->kitchen?->user?->phone ?? '').') কে ফোন করুন।';
            Push::toUser($guest->shahidar_id, 'গৃহিণী ৫ মিনিটে উত্তর দেননি', $body, '/shahidar', 'guest-'.$guest->id);
            User::role(Roles::ADMIN)->pluck('id')->each(fn ($id) => Push::toUser($id, 'অতিথির খাবার: গৃহিণী উত্তর দেননি', $body, '/admin', 'guest-'.$guest->id));
        }

        // "পারব" said, but 30 minutes on still no box (she has not posted this meal): the shahidar calls.
        $stuck = GuestMeal::where('status', 'cook_yes')->whereNull('order_id')->whereDate('service_date', BookingPlanner::today())
            ->where('cook_replied_at', '<=', now()->subMinutes(30))->with(['customer.user', 'kitchen.user'])->get()
            ->filter(fn (GuestMeal $g) => Cache::add('guest-yes-followup-'.$g->id, true, now()->addDay()));
        foreach ($stuck as $guest) {
            Push::toUser($guest->shahidar_id, 'অতিথির খাবার: এখনো বক্স হয়নি',
                "{$guest->customer->user->name}: {$guest->when()}, ".Order::banglaDigits($guest->plates).' প্লেট। গৃহিণী "পারব" বলেছেন কিন্তু পোস্ট দেননি — '
                .($guest->kitchen?->display_name ?? '').' ('.($guest->kitchen?->user?->phone ?? '').') কে ফোন করুন।', '/shahidar', 'guest-'.$guest->id);
        }

        return $late->count() + $stuck->count();
    }

    /** Live posts today for this meal with enough plates, the customer's area first. */
    public function candidatePosts(GuestMeal $guest): Collection
    {
        $areaId = $guest->customer->area_id;

        return Post::query()->visibleNow()->where('meal_slot', $guest->meal_slot->value)
            ->where('plates_left', '>=', $guest->plates)->whereDate('published_at', BookingPlanner::today())
            ->with('kitchen.area')->get()
            ->sortBy(fn (Post $p) => [$p->kitchen_id === $guest->kitchen_id ? 0 : 1, $p->kitchen->area_id === $areaId ? 0 : 1, $p->price_paisa])
            ->take(15)->values();
    }

    /** Her post for this meal today, whatever its state (sold out included). */
    private function todaysPostOf(GuestMeal $guest): ?Post
    {
        if ($guest->service_date->toDateString() !== BookingPlanner::today()) {
            return null;
        }

        return Post::where('kitchen_id', $guest->kitchen_id)->where('meal_slot', $guest->meal_slot->value)
            ->where('published_at', '>=', DhakaTime::startOfDay())->latest('id')->first();
    }

    private function livePostOf(GuestMeal $guest): ?Post
    {
        if ($guest->service_date->toDateString() !== BookingPlanner::today()) {
            return null;
        }

        return Post::query()->visibleNow()->where('kitchen_id', $guest->kitchen_id)
            ->where('meal_slot', $guest->meal_slot->value)->where('plates_left', '>=', $guest->plates)->latest('id')->first();
    }

    /** Requests still to sort out (and today's done ones), for shahidar/admin/cook screens. */
    public static function current(): Builder
    {
        return GuestMeal::query()->whereDate('service_date', '>=', BookingPlanner::today())
            ->with(['customer.user', 'customer.area', 'kitchen.user', 'shahidar', 'order', 'handler'])
            ->orderByRaw("FIELD(status, 'cook_no', 'asked', 'cook_yes', 'arranged', 'cancelled')")->orderBy('service_date')->latest('id');
    }

    private static function clean(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : mb_substr($text, 0, 300);
    }
}
