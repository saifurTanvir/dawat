<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PostStatus;
use App\Jobs\SendOrderSms;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Otp\OtpPurpose;
use App\Support\Roles;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * F29: "নিতে চাই" — a feed buyer books plates from a post at once. The food is
 * already cooked, so there is no approval step; the cook can decline within
 * 10 minutes. Delivery goes through the area's shahidar; "নিজে নেবেন" means
 * the cook phones the buyer, so her address never appears in the app.
 */
class PlaceOrder
{
    public const MAX_PLATES = 5;

    public const MAX_OPEN = 2;

    public function __invoke(Post $post, User $buyer, int $plates, string $receive, ?string $address, ?string $note): Order
    {
        $buyer->refresh(); // the no-show pause may have been set moments ago
        if (! $buyer->canOrder()) {
            throw ValidationException::withMessages(['order' => 'বারবার খাবার না নেওয়ায় "নিতে চাই" কিছুদিনের জন্য বন্ধ। দাওয়াত টিমের সাথে যোগাযোগ করুন।']);
        }
        $open = Order::where('buyer_id', $buyer->id)->open()->count();
        if ($open >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['order' => 'আপনার '.Order::banglaDigits(self::MAX_OPEN).'টা অর্ডার চলছে। সেগুলো পৌঁছালে আবার নিতে পারবেন।']);
        }

        return DB::transaction(function () use ($post, $buyer, $plates, $receive, $address, $note) {
            $post = Post::whereKey($post->id)->with('kitchen.area')->lockForUpdate()->firstOrFail();
            $kitchen = $post->kitchen;

            if ($kitchen->user_id === $buyer->id) {
                throw ValidationException::withMessages(['order' => 'নিজের রান্না নিজে অর্ডার করা যায় না।']);
            }
            if (! $kitchen->allow_order || ! $kitchen->isApproved() || $post->status !== PostStatus::Live || $post->available_until->isPast()) {
                throw ValidationException::withMessages(['order' => 'এই খাবার এখন অর্ডার করা যাচ্ছে না।']);
            }
            if ($plates > $post->plates_left) {
                throw ValidationException::withMessages(['plates' => 'এত প্লেট বাকি নেই। বাকি আছে '.Order::banglaDigits($post->plates_left).'টা।']);
            }

            $shahidarId = null;
            if ($receive === 'delivery') {
                $shahidarId = self::shahidarFor($post);
                if (! $shahidarId) {
                    throw ValidationException::withMessages(['receive' => 'এই এলাকায় এখনো শাহিদার নেই। "নিজে নেব" বেছে নিন।']);
                }
            }

            $post->plates_left -= $plates;
            if ($post->plates_left === 0) {
                $post->status = PostStatus::SoldOut;
            }
            $post->save();

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
                        'kitchen_id' => $kitchen->id,
                        'buyer_id' => $buyer->id,
                        'shahidar_id' => $shahidarId,
                        'receive' => $receive,
                        'delivery_address' => $receive === 'delivery' ? trim((string) $address) : null,
                        'buyer_note' => filled($note) ? trim($note) : null,
                        'title' => $post->title,
                        'plates' => $plates,
                        'food_paisa' => $post->price_paisa * $plates,
                        'delivery_fee_paisa' => $receive === 'delivery' ? (int) $kitchen->area?->delivery_fee_paisa : 0,
                        'status' => OrderStatus::Booked,
                        'delivery_code_hash' => Hash::make($code),
                        // Self pickup: the cook takes the cash herself.
                        'payment_method' => $receive === 'self' ? 'cash_to_cook' : 'cash',
                    ]);
                    break;
                } catch (QueryException $e) {
                    if ($attempt >= 4 || ! str_contains($e->getMessage(), 'orders_service_date_seq_unique')) {
                        throw $e;
                    }
                }
            }

            if (config('dawat.orders.sms')) {
                SendOrderSms::dispatch($order->id, $receive === 'self' ? OtpPurpose::OneOffSelf : OtpPurpose::OrderBooked, $code)->afterCommit();
                SendOrderSms::dispatch($order->id, OtpPurpose::CookNewOrder, '', 'cook')->afterCommit();
            }

            return $order;
        });
    }

    /** Whether "শাহিদার পৌঁছে দেবেন" is offered for this post. */
    public static function deliveryAvailable(Post $post): bool
    {
        return $post->kitchen?->area_id !== null
            && User::role(Roles::SHAHIDAR)->whereHas('shahidarAreas', fn ($q) => $q->whereKey($post->kitchen->area_id))->exists();
    }

    /** The cook's regular shahidar if she has one, else the area's least busy today. */
    public static function shahidarFor(Post $post): ?int
    {
        $areaId = $post->kitchen->area_id;
        $usual = Pairing::open()->where('kitchen_id', $post->kitchen_id)
            ->whereHas('shahidar.shahidarAreas', fn ($q) => $q->whereKey($areaId))
            ->selectRaw('shahidar_id, COUNT(*) AS n')->groupBy('shahidar_id')->orderByDesc('n')->value('shahidar_id');
        if ($usual) {
            return (int) $usual;
        }

        return User::role(Roles::SHAHIDAR)
            ->whereHas('shahidarAreas', fn ($q) => $q->whereKey($areaId))
            ->withCount(['shahidarOrders as today_count' => fn ($q) => $q->whereDate('service_date', BookingPlanner::today())->open()])
            ->orderBy('today_count')
            ->value('id');
    }
}
