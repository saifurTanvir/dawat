<?php

namespace App\Services\Admin;

use App\Actions\PublishPost;
use App\Enums\OrderStatus;
use App\Filament\Support\Links;
use App\Models\BoxReview;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\KitchenAction;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Orders\Settlements;
use App\Services\Reviews\BoxReviews;
use Illuminate\Support\Carbon;

/**
 * F49: the numbers at the top of each person's history in the admin panel.
 * All-time; the tables below filter by date.
 */
class History
{
    private function bn(int|float|string $n): string
    {
        return Order::banglaDigits((string) $n);
    }

    private function tk(int $paisa): string
    {
        return '৳'.$this->bn(number_format(intdiv($paisa, 100)));
    }

    private function pct(int $part, int $whole): string
    {
        return $whole ? $this->bn((string) round(100 * $part / $whole)).'%' : '—';
    }

    /** @return list<array{label: string, value: string, hint?: string}> */
    public function kitchen(Kitchen $k): array
    {
        $orders = Order::where('kitchen_id', $k->id);
        $all = (clone $orders)->count();
        $plates = (int) (clone $orders)->where('status', '!=', OrderStatus::Cancelled)->sum('plates');
        $delivered = (clone $orders)->where('status', OrderStatus::Delivered)->count();
        $cancelled = (clone $orders)->where('status', OrderStatus::Cancelled)->count();
        $paid = (int) Settlement::where('kitchen_id', $k->id)->where('kind', 'cook')->sum('amount_paisa');
        $due = (int) (app(Settlements::class)->cooksDue()->firstWhere(fn ($r) => $r['kitchen']->id === $k->id)['payable'] ?? 0);

        return [
            ['label' => 'পোস্ট', 'value' => $this->bn($k->posts()->count())],
            ['label' => 'বক্স', 'value' => $this->bn($all), 'hint' => $this->bn($plates).' প্লেট'],
            ['label' => 'পৌঁছেছে', 'value' => $this->bn($delivered)],
            ['label' => 'বাতিল', 'value' => $this->bn($cancelled), 'hint' => $this->pct($cancelled, $all)],
            ['label' => 'দাওয়াত পরিশোধ করেছে', 'value' => $this->tk($paid)],
            ['label' => 'এখন পাওনা', 'value' => $this->tk($due)],
            ['label' => 'রেটিং', 'value' => $k->rating_count ? '★ '.$this->bn(number_format((float) $k->rating_avg, 1)) : '—', 'hint' => $this->bn((int) $k->rating_count).'টা রিভিউ'],
        ];
    }

    /** @return list<array{label: string, value: string, hint?: string}> */
    public function shahidar(User $u): array
    {
        $orders = Order::where('shahidar_id', $u->id);
        $delivered = (clone $orders)->where('status', OrderStatus::Delivered)->count();
        $failed = (clone $orders)->where('status', OrderStatus::Failed)->count();
        $collected = (int) Order::where('collected_by', $u->id)->where('payment_method', 'cash')->whereNotNull('paid_at')->sum(\DB::raw('food_paisa + delivery_fee_paisa'));
        $handed = (int) Settlement::where('shahidar_id', $u->id)->where('kind', 'shahidar')->sum('amount_paisa');
        $reviews = BoxReview::whereIn('order_id', (clone $orders)->select('id'));
        $count = (clone $reviews)->count();

        return [
            ['label' => 'বক্স', 'value' => $this->bn((clone $orders)->count())],
            ['label' => 'পৌঁছেছেন', 'value' => $this->bn($delivered)],
            ['label' => 'পৌঁছানো যায়নি', 'value' => $this->bn($failed), 'hint' => $this->pct($failed, $delivered + $failed)],
            ['label' => 'নগদ তুলেছেন', 'value' => $this->tk($collected)],
            ['label' => 'দাওয়াতকে দিয়েছেন', 'value' => $this->tk($handed)],
            ['label' => 'এখন হাতে', 'value' => $this->tk(app(Settlements::class)->cashHeldBy($u))],
            ['label' => 'তাঁর বক্সের রেটিং', 'value' => $count ? '★ '.$this->bn(number_format((float) (clone $reviews)->avg('stars'), 1)) : '—', 'hint' => $this->bn($count).'টা রিভিউ'],
            ['label' => 'চলমান জুটি', 'value' => $this->bn($u->shahidarPairings()->whereNull('ended_at')->count())],
        ];
    }

    /** A regular customer and/or a feed buyer. @return list<array{label: string, value: string, hint?: string}> */
    public function customer(User $u, ?Customer $c = null): array
    {
        $c ??= $u->customer;
        $orders = Order::where(fn ($q) => $q->where('buyer_id', $u->id)->when($c, fn ($q) => $q->orWhere('customer_id', $c->id)));
        $delivered = (clone $orders)->whereIn('status', [OrderStatus::Delivered, OrderStatus::Failed]);
        $skipped = (clone $orders)->where('status', OrderStatus::Cancelled)->where('cancelled_by', 'customer')->count();
        $owed = (int) (clone $delivered)->whereNull('paid_at')->where('payment_method', '!=', 'cash_to_cook')->sum(\DB::raw('food_paisa + delivery_fee_paisa'));
        $billDue = $c ? (int) $c->bills()->whereIn('status', ['open', 'partial'])->get()->sum(fn ($b) => $b->duePaisa()) : 0;

        return [
            ['label' => 'বক্স', 'value' => $this->bn((clone $orders)->count())],
            ['label' => 'পৌঁছেছে', 'value' => $this->bn((clone $orders)->where('status', OrderStatus::Delivered)->count())],
            ['label' => '"আজ লাগবে না"', 'value' => $this->bn($skipped)],
            ['label' => 'মোট খাবার', 'value' => $this->tk((int) (clone $delivered)->sum(\DB::raw('food_paisa + delivery_fee_paisa')))],
            ['label' => 'বাকি', 'value' => $this->tk($owed), 'hint' => $billDue ? 'এর মধ্যে বিলে '.$this->tk($billDue) : null],
            ['label' => 'রিভিউ দিয়েছেন', 'value' => $this->bn(BoxReview::where('user_id', $u->id)->count())],
        ];
    }

    /**
     * Everything that happened to one box, oldest first.
     *
     * @return list<array{at: ?Carbon, title: string, detail: ?string, url: ?string}>
     */
    public function timeline(Order $o): array
    {
        $o->loadMissing(['kitchen', 'shahidar', 'post', 'bill', 'review.replier', 'sticker']);
        $by = fn (?int $id) => $id ? (User::find($id)?->name ?: User::find($id)?->phone) : null;
        $events = [];
        $add = function ($at, string $title, ?string $detail = null, ?string $url = null) use (&$events) {
            $events[] = ['at' => $at, 'title' => $title, 'detail' => $detail, 'url' => $url];
        };

        $add($o->created_at, $o->isOneOff() ? 'ফিড থেকে অর্ডার' : 'নিয়মিত বক্স বুক হলো',
            $o->post ? 'পোস্ট: '.$o->post->title.($o->post->source && $o->post->source !== 'cook' ? ' ('.(PublishPost::SOURCES[$o->post->source] ?? '').')' : '') : null,
            Links::post($o->post_id));
        if ($o->ask_until) {
            $add($o->created_at, 'দাম গ্রাহকের সীমার বেশি — জিজ্ঞেস', 'উত্তরের শেষ সময় '.$o->ask_until->timezone(config('app.display_timezone'))->format('g:i a'));
        }
        if ($o->ready_at) {
            $actor = KitchenAction::where('kitchen_id', $o->kitchen_id)->where('action', 'ready')
                ->whereBetween('created_at', [$o->ready_at->copy()->subMinute(), $o->ready_at->copy()->addMinute()])->with('user')->first();
            $add($o->ready_at, 'রান্না রেডি', trim(($actor ? $actor->whoLabel().' জানিয়েছেন' : 'গৃহিণী জানিয়েছেন').($o->ready_note ? ' · “'.$o->ready_note.'”' : '')));
        }
        if ($o->picked_at) {
            $add($o->picked_at, 'শাহিদার খাবার তুলেছেন', trim(($o->shahidar?->name ?? '').($o->sticker ? ' · স্টিকার '.$o->sticker->displayCode() : '')));
        }
        if ($o->delivered_at) {
            $add($o->delivered_at, 'পৌঁছেছে', BoxReviews::deliveredBy($o)
                .match ($o->confirm_method) {
                    'shahidar_code' => ' · গ্রাহকের কোড দিয়ে', 'shahidar_tap' => ' · কোড ছাড়া (যাচাই বাকি)', default => ''
                });
        }
        if ($o->status === OrderStatus::Failed) {
            $add($o->updated_at, 'পৌঁছানো যায়নি', $o->failure_reason.($o->confirmed_by ? ' · '.$by($o->confirmed_by) : ''));
        }
        if ($o->status === OrderStatus::Cancelled) {
            $who = ['customer' => 'গ্রাহক', 'cook' => 'গৃহিণী', 'admin' => 'দাওয়াত টিম', 'system' => 'নিজে থেকে'][$o->cancelled_by] ?? $o->cancelled_by;
            $add($o->updated_at, 'বাতিল', trim(($who ? $who.' · ' : '').($o->cancel_reason ?? '')));
        }
        if ($o->paid_at) {
            $add($o->paid_at, 'টাকা পাওয়া গেছে', BoxReviews::paymentLabel($o).($o->collected_by ? ' · তুলেছেন '.$by($o->collected_by) : ''));
        }
        if ($o->shahidar_settlement_id && ($s = Settlement::find($o->shahidar_settlement_id))) {
            $add($s->created_at, 'শাহিদার দাওয়াতকে টাকা জমা দিয়েছেন', 'জমা নিয়েছেন '.($by($s->settled_by) ?? '—'));
        }
        if ($o->cook_settlement_id && ($s = Settlement::find($o->cook_settlement_id))) {
            $add($s->created_at, 'গৃহিণীকে পরিশোধ', $this->tk($s->amount_paisa).' (একসাথে '.$this->bn($s->orders_count).'টা বক্স)'.($s->note ? ' · '.$s->note : ''));
        }
        if ($o->bill) {
            $add($o->bill->created_at, 'বিলে যোগ হলো', $o->bill->number.' · '.$o->bill->statusLabel(), Links::bill($o->bill_id));
        }
        if ($o->review) {
            $add($o->review->created_at, 'রিভিউ '.$o->review->stars(), $o->review->body);
            if ($o->review->reply) {
                $add($o->review->replied_at, 'রিভিউর উত্তর', $o->review->replyLabel().': '.$o->review->reply.($o->review->reply_hidden ? ' (লুকানো)' : ''));
            }
        }
        foreach (KitchenAction::where('order_id', $o->id)->with('user')->get() as $a) {
            $add($a->created_at, 'গৃহিণীর হয়ে', $a->whoLabel().' · '.$a->details);
        }

        usort($events, fn ($a, $b) => ($a['at']?->getTimestamp() ?? 0) <=> ($b['at']?->getTimestamp() ?? 0));

        return $events;
    }
}
