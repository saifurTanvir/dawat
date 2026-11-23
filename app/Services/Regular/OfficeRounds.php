<?php

namespace App\Services\Regular;

use App\Enums\OrderStatus;
use App\Jobs\SendRoundSms;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Order;
use App\Models\RoundStop;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Orders\Settlements;
use App\Services\Push\Push;
use App\Support\ContactHours;
use App\Support\Geo\GeoPoint;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F95: an office round. The area manager collects one office's boxes house
 * by house, following the map, then hands them over at the office. The next
 * house is always the one he can reach soonest (a cook who said "10 more
 * minutes" waits her turn); every cook hears about 30 minutes before he
 * comes, and again when he leaves the house before hers. App notification
 * first; an SMS when she has none turned on.
 *
 * F110: the evening return round is the same walk the other way: the office's
 * empty boxes go back to each cook, and she is paid at her door from the cash
 * the area manager holds — daily, or on her weekly day — then confirms it.
 *
 * The area manager on the round sees the cooks' and the office's pins — the
 * owner's decision; nobody else does (CLAUDE.md).
 */
class OfficeRounds
{
    public const PICKUP = 'pickup';

    public const RETURN = 'return';

    /** Makes or refreshes the stops for this drop and area manager (who has which boxes may change until he leaves). */
    public function plan(OfficeDrop $drop, User $shahidar, string $kind = self::PICKUP): Collection
    {
        return DB::transaction(function () use ($drop, $shahidar, $kind) {
            // Going back: the boxes that reached the office return to the cooks who sent them.
            $orders = $drop->orders()->where('shahidar_id', $shahidar->id)
                ->whereIn('status', $kind === self::RETURN ? [OrderStatus::Delivered] : [OrderStatus::Booked, OrderStatus::PickedUp, OrderStatus::Delivered])->get();
            $stops = RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->where('kind', $kind)
                ->lockForUpdate()->get()->keyBy('kitchen_id');
            $first = $stops->first();
            $started = $first?->started_at;

            foreach ($orders->groupBy('kitchen_id') as $kitchenId => $boxes) {
                $stop = $stops[$kitchenId] ?? new RoundStop(['office_drop_id' => $drop->id, 'shahidar_id' => $shahidar->id, 'kitchen_id' => $kitchenId, 'kind' => $kind,
                    'started_at' => $started, 'start_lat' => $first?->start_lat, 'start_lng' => $first?->start_lng]);
                if (! $stop->picked_at) {
                    $stop->boxes = $boxes->count();
                }
                if ($kind === self::PICKUP && ! $boxes->contains('status', OrderStatus::Booked)) {
                    $stop->picked_at ??= $boxes->max('picked_at') ?? now();
                }
                $stop->save();
                $stops[$kitchenId] = $stop;
            }
            // A cook whose boxes were all cancelled is no longer on the way.
            foreach ($stops as $kitchenId => $stop) {
                if (! $orders->contains('kitchen_id', $kitchenId) && ! $stop->picked_at) {
                    $stop->delete();
                    $stops->forget($kitchenId);
                }
            }

            $stops = $stops->values();
            [$from, $at] = $started ? $this->whereNow($stops) : [null, now()];

            return $this->arrange($drop, $stops, $from, $at);
        });
    }

    /** "রওনা দিচ্ছি": the round begins from where he is now (if the phone told us). */
    public function start(OfficeDrop $drop, User $shahidar, ?GeoPoint $here, string $kind = self::PICKUP): Collection
    {
        if ($kind === self::RETURN && $drop->orders()->where('shahidar_id', $shahidar->id)->whereIn('status', [OrderStatus::Booked, OrderStatus::PickedUp])->exists()) {
            throw ValidationException::withMessages(['round' => 'আগে অফিসে সব বক্স বুঝিয়ে দিন, তারপর ফেরত রাউন্ড।']);
        }
        $stops = $this->plan($drop, $shahidar, $kind);
        if ($stops->whereNull('picked_at')->isEmpty()) {
            throw ValidationException::withMessages(['round' => $kind === self::RETURN ? 'ফেরত দেওয়ার মতো কোনো বক্স নেই।' : 'এই অফিসের তোলার মতো কোনো বক্স নেই।']);
        }
        // The return round sets out from the office, where the empty boxes are.
        $here ??= $kind === self::RETURN ? $drop->company?->location : null;
        $first = $stops->first()->started_at === null;
        RoundStop::whereIn('id', $stops->pluck('id'))->whereNull('started_at')
            ->update(['started_at' => now(), 'start_lat' => $here?->lat, 'start_lng' => $here?->lng]);
        $stops = $this->arrange($drop, $this->fresh($drop, $shahidar, $kind), $here, now());
        if ($first) {
            $this->tellNext($stops);
        }
        $this->tellSoon($stops);

        return $stops;
    }

    /** Called after a cook's boxes for the drop were picked up: on to the next house. */
    public function picked(OfficeDrop $drop, Kitchen $kitchen, User $shahidar): void
    {
        $stop = RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->where('kitchen_id', $kitchen->id)->where('kind', self::PICKUP)->first();
        if (! $stop) {
            return;
        }
        $stop->forceFill(['picked_at' => now()])->save();
        if (! $stop->started_at) {
            return; // picked up without a round: nothing to tell anyone
        }
        $stops = $this->arrange($drop, $this->fresh($drop, $shahidar), $kitchen->location, now());
        $this->tellNext($stops);
        $this->tellSoon($stops);
    }

    /**
     * F110: "বক্স ও টাকা দিয়েছি" — her empty boxes are back, and on her pay day
     * everything she is owed is paid from the cash he holds. She is asked to confirm.
     */
    public function returned(RoundStop $stop, User $shahidar): RoundStop
    {
        abort_unless($stop->isReturn() && $stop->shahidar_id === $shahidar->id, 404);
        $stop = DB::transaction(function () use ($stop, $shahidar) {
            $stop = RoundStop::whereKey($stop->id)->lockForUpdate()->firstOrFail();
            if ($stop->picked_at) {
                throw ValidationException::withMessages(['stop' => 'এই বাসায় বক্স আগেই ফেরত দেওয়া হয়েছে।']);
            }
            $kitchen = $stop->kitchen;
            $settlement = null;
            if ($kitchen->paidOn(now('Asia/Dhaka')) && app(Settlements::class)->dueFor($kitchen)['payable'] > 0) {
                $settlement = app(Settlements::class)->payCook($kitchen, $shahidar, 'ফেরত রাউন্ডে এলাকা ম্যানেজার নগদে দিয়েছেন', $shahidar);
            }
            $stop->forceFill(['picked_at' => now(), 'settlement_id' => $settlement?->id, 'paid_paisa' => (int) $settlement?->amount_paisa])->save();

            return $stop;
        });

        $paid = $stop->paid_paisa ? ' আর ৳'.Order::banglaDigits(number_format(intdiv($stop->paid_paisa, 100))) : '';
        Push::toUser($stop->kitchen->user, 'বক্স'.($paid ? ' আর টাকা' : '').' দিয়ে গেলেন — পেয়েছেন?',
            'এলাকা ম্যানেজার জানিয়েছেন '.Order::banglaDigits($stop->boxes).'টা বক্স'.$paid.' দিয়েছেন। অ্যাপে "হ্যাঁ, পেয়েছি" বা "পাইনি" চাপুন।',
            '/cook', 'round-'.$stop->id.'-done');

        $stops = $this->arrange($stop->drop, $this->fresh($stop->drop, $shahidar, self::RETURN), $stop->kitchen->location, now());
        $this->tellNext($stops);
        $this->tellSoon($stops);

        return $stop;
    }

    /** F110: the cook says she got the boxes (and the money) — or did not. */
    public function confirm(RoundStop $stop, bool $got): RoundStop
    {
        abort_unless($stop->isReturn() && $stop->picked_at, 422, 'এলাকা ম্যানেজার এখনো বক্স দিয়েছেন বলে জানাননি।');
        $stop->forceFill($got ? ['cook_confirmed_at' => now(), 'cook_disputed_at' => null] : ['cook_disputed_at' => now(), 'cook_confirmed_at' => null])->save();
        if (! $got) {
            Push::toUser($stop->shahidar_id, $stop->kitchen->display_name.' বলছেন বক্স বা টাকা পাননি',
                'ফোন করে মিলিয়ে নিন। দাওয়াত টিমও দেখছে।', '/shahidar/return/'.$stop->office_drop_id, 'round-dispute-'.$stop->id);
        }

        return $stop;
    }

    /** "পরে আসব": she is not ready, go to the others first. */
    public function later(RoundStop $stop): Collection
    {
        abort_if($stop->picked_at !== null, 422, 'এই বাসা থেকে বক্স আগেই তোলা হয়েছে।');
        $stop->increment('later');
        $drop = $stop->drop;
        $stops = $this->fresh($drop, $stop->shahidar, $stop->kind);
        [$from, $at] = $this->whereNow($stops);
        $stops = $this->arrange($drop, $stops, $from, $at);
        if ($stop->started_at) {
            $this->tellNext($stops);
        }

        return $stops;
    }

    /** The cook answers: "তৈরি আছি" or "আরও ১০ মিনিট লাগবে". The area manager hears it. */
    public function reply(RoundStop $stop, string $reply): RoundStop
    {
        abort_if($stop->picked_at !== null, 422, 'বক্স আগেই নেওয়া হয়ে গেছে।');
        $late = $reply === 'late';
        $stop->forceFill([
            'cook_reply' => $late ? 'late' : 'ready',
            'cook_ready_at' => $late ? now()->addMinutes((int) config('dawat.rounds.late_minutes', 10)) : null,
        ])->save();

        $stops = $this->fresh($stop->drop, $stop->shahidar, $stop->kind);
        if ($stop->started_at) {
            [$from, $at] = $this->whereNow($stops);
            $stops = $this->arrange($stop->drop, $stops, $from, $at);
            $this->tellNext($stops);
        }
        $name = $stop->kitchen->display_name;
        $back = $stop->isReturn();
        Push::toUser($stop->shahidar_id,
            $late ? ($back ? "{$name}: ১০ মিনিট পরে আসতে বলেছেন" : "{$name}: আরও ১০ মিনিট লাগবে") : ($back ? "{$name}: বাসায় আছেন" : "{$name}: বক্স তৈরি আছে"),
            $late ? 'আগে অন্য বাসায় যান, তালিকা নিজে থেকে বদলে গেছে।' : ($back ? 'গেলেই পাবেন।' : 'গেলেই বক্স পাবেন।'),
            ($back ? '/shahidar/return/' : '/shahidar/round/').$stop->office_drop_id, 'round-reply-'.$stop->id);

        return $stop->fresh();
    }

    /** Every minute: times move on, so the cooks whose turn is within 30 minutes are told. */
    public function tick(): int
    {
        $n = 0;
        RoundStop::query()->started()->open()
            ->whereHas('drop', fn ($q) => $q->whereDate('service_date', BookingPlanner::today()))
            ->get()->groupBy(fn (RoundStop $s) => $s->office_drop_id.'-'.$s->shahidar_id.'-'.$s->kind)
            ->each(function (Collection $group) use (&$n) {
                $first = $group->first();
                $stops = $this->fresh($first->drop, $first->shahidar, $first->kind);
                [$from, $at] = $this->whereNow($stops);
                $n += $this->tellSoon($this->arrange($first->drop, $stops, $from, $at));
            });

        return $n;
    }

    /** The round, for the area manager's screen. Pins included: he needs the way there. */
    public function forShahidar(OfficeDrop $drop, User $shahidar, string $kind = self::PICKUP): array
    {
        $stops = $this->plan($drop, $shahidar, $kind);
        $company = $drop->company;
        $open = $stops->whereNull('picked_at')->values();

        return [
            'drop' => OfficeDrops::row($drop->load(['company.contact', 'company.area', 'stickers', 'orders' => fn ($q) => $q->where('shahidar_id', $shahidar->id)->with(['customer.user', 'kitchen.user', 'kitchen.area'])->orderBy('seq')])),
            'kind' => $kind,
            'started' => $stops->first()?->started_at !== null,
            // F110: the return round's money
            'paid_today_paisa' => (int) $stops->sum('paid_paisa'),
            'cash_held_paisa' => $kind === self::RETURN ? app(Settlements::class)->cashHeldBy($shahidar) : null,
            'drop_done' => $drop->delivered_at !== null,
            'total' => $stops->count(),
            'picked' => $stops->whereNotNull('picked_at')->count(),
            'next_id' => $open->first()?->id,
            'office' => [
                'name' => $company->name,
                'address' => $company->address,
                'note' => $company->drop_note,
                'map_url' => self::mapUrl($company->location, $company->address.', '.($company->area?->name ?? 'ঢাকা')),
                'has_pin' => $company->location !== null,
            ],
            'stops' => $stops->map(fn (RoundStop $s) => $this->stopRow($s))->values(),
        ];
    }

    /**
     * Today's visits to this cook, for her app: coming to pick up, coming with
     * the empty boxes (and her money), and the return she still has to confirm.
     * No office or customer names.
     */
    public static function forCook(Kitchen $kitchen): Collection
    {
        $clock = fn ($t) => $t ? ContactHours::clock($t->timezone('Asia/Dhaka')->format('H:i')) : null;

        return RoundStop::query()->where('kitchen_id', $kitchen->id)
            ->where(fn ($q) => $q->whereNull('picked_at')->orWhere(fn ($q) => $q->where('kind', self::RETURN)->where('picked_at', '>', now()->subHours(20))))
            ->whereHas('drop', fn ($q) => $q->whereDate('service_date', BookingPlanner::today()))
            ->with(['drop', 'kitchen'])->orderBy('kind')->orderBy('eta_at')->get()
            ->map(function (RoundStop $s) use ($clock, $kitchen) {
                $back = $s->isReturn();
                $due = $back && ! $s->picked_at && $kitchen->paidOn(now('Asia/Dhaka')) ? app(Settlements::class)->dueFor($kitchen)['payable'] : 0;

                return [
                    'id' => $s->id,
                    'kind' => $s->kind,
                    'meal_slot_label' => $s->drop->meal_slot->label(),
                    'boxes' => $s->boxes,
                    'started' => $s->started_at !== null,
                    'eta' => $s->started_at && ! $s->picked_at ? $clock($s->eta_at) : null,
                    'coming_now' => $s->next_sent_at !== null && ! $s->picked_at,
                    'reply' => $s->cook_reply,
                    'ready_at' => $clock($s->cook_ready_at),
                    // F110
                    'pay_due_paisa' => $due,
                    'pay_label' => $back ? $kitchen->payLabel() : null,
                    'done' => $s->picked_at !== null,
                    'paid_paisa' => (int) $s->paid_paisa,
                    'confirmed' => $s->cook_confirmed_at !== null,
                    'disputed' => $s->cook_disputed_at !== null,
                ];
            })->values();
    }

    private function stopRow(RoundStop $s): array
    {
        $k = $s->kitchen;
        $address = $k->pickup_address ?: $k->area?->name;

        return [
            'id' => $s->id,
            'kitchen_id' => $k->id,
            'name' => $k->display_name,
            'phone' => $k->user->phone,
            'contact' => ContactHours::card($k->user, [$s->drop->meal_slot], $s->isReturn()
                ? 'আসসালামু আলাইকুম, দাওয়াত থেকে এলাকা ম্যানেজার বলছি। খালি বক্স ফেরত দিতে আসছি।'
                : 'আসসালামু আলাইকুম, দাওয়াত থেকে এলাকা ম্যানেজার বলছি। অফিসের বক্স নিতে আসছি।'),
            'address' => $address,
            'map_url' => self::mapUrl($k->location, trim(($address ?? '').', '.($k->area?->name ?? 'ঢাকা'), ', ')),
            'has_pin' => $k->location !== null,
            'boxes' => $s->boxes,
            'position' => $s->position,
            'eta' => $s->started_at && $s->eta_at && ! $s->picked_at ? ContactHours::clock($s->eta_at->timezone('Asia/Dhaka')->format('H:i')) : null,
            'picked' => $s->picked_at !== null,
            'reply' => $s->cook_reply,
            'ready_at' => $s->cook_ready_at && $s->cook_ready_at->isFuture() ? ContactHours::clock($s->cook_ready_at->timezone('Asia/Dhaka')->format('H:i')) : null,
            'told' => $s->next_sent_at ? 'now' : ($s->soon_sent_at ? 'soon' : null),
            'later' => $s->later,
            // F110: on the return round, what to pay her today (her whole due on her pay day)
            ...($s->isReturn() ? $this->payRow($s) : []),
        ];
    }

    private function payRow(RoundStop $s): array
    {
        $k = $s->kitchen;
        $dueToday = ! $s->picked_at && $k->paidOn(now('Asia/Dhaka'));
        $due = $dueToday ? app(Settlements::class)->dueFor($k) : null;

        return [
            'pay_due_paisa' => (int) ($due['payable'] ?? 0),
            'pay_orders' => (int) ($due['orders'] ?? 0),
            'pay_label' => $k->payLabel(),
            'pay_today' => $k->paidOn(now('Asia/Dhaka')),
            'paid_paisa' => (int) $s->paid_paisa,
            'confirmed' => $s->cook_confirmed_at !== null,
            'disputed' => $s->cook_disputed_at !== null,
        ];
    }

    /** Google Maps directions to the pin, or a search for the address when there is no pin. */
    public static function mapUrl(?GeoPoint $point, ?string $address): ?string
    {
        if ($point) {
            return sprintf('https://www.google.com/maps/dir/?api=1&destination=%.6f,%.6f', $point->lat, $point->lng);
        }

        return filled($address) ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null;
    }

    /** @return Collection<int, RoundStop> */
    private function fresh(OfficeDrop $drop, User $shahidar, string $kind = self::PICKUP): Collection
    {
        return RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->where('kind', $kind)->with(['kitchen.user', 'kitchen.area', 'drop'])->get();
    }

    /** Where he is now: the last house he picked up from (and when), or nowhere known since the start. */
    private function whereNow(Collection $stops): array
    {
        $last = $stops->whereNotNull('picked_at')->sortByDesc('picked_at')->first();
        if ($last && $last->started_at && $last->picked_at->gte($last->started_at)) {
            return [$last->kitchen->location, $last->picked_at];
        }

        $first = $stops->first();
        $start = $first && $first->start_lat !== null ? GeoPoint::make($first->start_lat, $first->start_lng) : null;

        return [$start, $first?->started_at ?? now()];
    }

    /**
     * Puts the open stops in order and times them: from where he is, always the
     * house he can be at soonest (counting a cook who asked for 10 more minutes),
     * "later" ones last, houses without a pin after those with one.
     */
    private function arrange(OfficeDrop $drop, Collection $stops, ?GeoPoint $from = null, ?CarbonInterface $at = null): Collection
    {
        $stops->loadMissing(['kitchen.user', 'kitchen.area', 'drop']);
        $office = $drop->company?->location;
        $at = ($at ?? now())->copy();
        // Travelling: he cannot be anywhere sooner than now.
        $t = $at->lt(now()) ? now()->copy() : $at;
        $leftAt = $at;
        $pos = $from;
        $open = $stops->whereNull('picked_at')->values();
        $done = $stops->whereNotNull('picked_at')->sortBy('picked_at')->values();
        $i = $done->count();
        foreach ($done as $k => $stop) {
            $stop->position = $k + 1;
        }

        while ($open->isNotEmpty()) {
            $best = $open->sortBy(function (RoundStop $s) use ($pos, $leftAt, $office) {
                // By the time he could be there, not clamped to now: otherwise every house ties once time has passed.
                $arrive = $leftAt->copy()->addSeconds($this->travelSeconds($pos, $s->kitchen->location));
                $ready = $s->cook_ready_at && $s->cook_ready_at->gt($arrive) ? $s->cook_ready_at : $arrive;
                // The cook already told "he is coming to you now" stays next — unless she asked for more time.
                $promised = $s->next_sent_at && ! ($s->cook_ready_at && $s->cook_ready_at->gt($arrive)) ? 0 : 1;
                // Without a known start, the house farthest from the office first, to end near it.
                $far = $pos === null && $office && $s->kitchen->location ? -$office->distanceTo($s->kitchen->location) : 0;

                return sprintf('%03d|%d|%d|%012d|%012d|%08d', $s->later, $promised, $s->kitchen->location ? 0 : 1, $ready->getTimestamp(), (int) ($far + 1e9), $s->kitchen_id);
            })->first();
            $arrive = $leftAt->copy()->addSeconds($this->travelSeconds($pos, $best->kitchen->location));
            $arrive = $arrive->lt($t) ? $t->copy() : $arrive;
            $eta = $best->cook_ready_at && $best->cook_ready_at->gt($arrive) ? $best->cook_ready_at->copy() : $arrive;
            $best->position = ++$i;
            $best->eta_at = $eta;
            $leftAt = $eta->copy()->addMinutes((int) config('dawat.rounds.stop_minutes', 5));
            $t = $leftAt;
            $pos = $best->kitchen->location ?? $pos;
            $open = $open->reject(fn (RoundStop $s) => $s->id === $best->id)->values();
        }

        foreach ($stops as $stop) {
            if ($stop->isDirty(['position', 'eta_at'])) {
                $stop->saveQuietly();
            }
        }

        return $stops->sortBy('position')->values();
    }

    private function travelSeconds(?GeoPoint $from, ?GeoPoint $to): int
    {
        if (! $from || ! $to) {
            return 10 * 60; // unknown: about ten minutes
        }
        $metresPerSecond = max(1.0, (float) config('dawat.rounds.speed_kmh', 15)) * 1000 / 3600;

        return (int) round($from->distanceTo($to) * (float) config('dawat.rounds.road_factor', 1.4) / $metresPerSecond);
    }

    /** The first open house: "he is on his way to you now". */
    private function tellNext(Collection $stops): void
    {
        $next = $stops->whereNull('picked_at')->sortBy('position')->first();
        if (! $next || $next->next_sent_at) {
            return;
        }
        $minutes = max(1, (int) ceil(now()->diffInSeconds($next->eta_at, false) / 60));
        $in = 'প্রায় '.Order::banglaDigits($minutes).' মিনিটে পৌঁছাবেন। ';
        if ($next->isReturn()) {
            $this->tell($next, 'next', 'এলাকা ম্যানেজার এখন বক্স ফেরত দিতে আসছেন', $in.$this->returnLine($next), 'প্রায় '.Order::banglaDigits($minutes).' মিনিটের মধ্যে');

            return;
        }
        $this->tell($next, 'next', 'এলাকা ম্যানেজার এখন আপনার কাছে আসছেন',
            $in.'অফিসের '.Order::banglaDigits($next->boxes).'টা বক্স তৈরি রাখুন।',
            'প্রায় '.Order::banglaDigits($minutes).' মিনিটের মধ্যে');
    }

    /** Houses he will reach within the notice time: "coming at about 12:20". */
    private function tellSoon(Collection $stops): int
    {
        $n = 0;
        $limit = now()->addMinutes((int) config('dawat.rounds.notice_minutes', 30));
        foreach ($stops->whereNull('picked_at') as $stop) {
            if ($stop->soon_sent_at || ! $stop->started_at || ! $stop->eta_at || $stop->eta_at->gt($limit)) {
                continue;
            }
            if ($stop->next_sent_at) {
                $stop->forceFill(['soon_sent_at' => now()])->save(); // already told he is coming now

                continue;
            }
            $when = ContactHours::clock($stop->eta_at->timezone('Asia/Dhaka')->format('H:i'));
            if ($stop->isReturn()) {
                $this->tell($stop, 'soon', 'বক্স ফেরত দিতে আসছেন: আনুমানিক '.$when, $this->returnLine($stop), 'আনুমানিক '.$when.'-এ');
                $n++;

                continue;
            }
            $this->tell($stop, 'soon', 'এলাকা ম্যানেজার আসছেন: আনুমানিক '.$when,
                'অফিসের '.Order::banglaDigits($stop->boxes).'টা বক্স নিতে আসবেন। তৈরি রাখুন — দেরি হলে "আরও ১০ মিনিট লাগবে" চাপুন।',
                'আনুমানিক '.$when.'-এ');
            $n++;
        }

        return $n;
    }

    /** "৩টা খালি বক্স ফেরত, আর আজকের টাকা ৳৪২০।" / "… আপনার টাকা প্রতি বৃহস্পতিবার।" */
    private function returnLine(RoundStop $stop): string
    {
        $k = $stop->kitchen;
        $boxes = Order::banglaDigits($stop->boxes).'টা খালি বক্স ফেরত';
        if (! $k->paidOn(now('Asia/Dhaka'))) {
            return $boxes.'। আপনার টাকা '.$k->payLabel().'।';
        }
        $due = app(Settlements::class)->dueFor($k)['payable'];

        return $boxes.($due > 0 ? ', আর আজ পাবেন ৳'.Order::banglaDigits(number_format(intdiv($due, 100))).'।' : '।');
    }

    private function tell(RoundStop $stop, string $kind, string $title, string $body, string $when): void
    {
        $user = $stop->kitchen->user;
        $column = $kind === 'next' ? 'next_sent_at' : 'soon_sent_at';
        $stop->forceFill([$column => now()])->save();
        Push::toUser($user, $title, $body, '/cook', 'round-'.$stop->id.'-'.$kind);

        // No app notifications on her phone: an SMS instead (one per round, a second only if the first was long ago).
        $hasPush = Push::enabled() && $user->pushSubscriptions()->exists();
        $again = $kind === 'next' && $stop->soon_sent_at && $stop->soon_sent_at->lt(now()->subMinutes(15));
        if (! $hasPush && config('dawat.rounds.sms') && (! $stop->sms_sent || $again)) {
            $stop->forceFill(['sms_sent' => true])->save();
            SendRoundSms::dispatch($stop->id, $when, $stop->isReturn() ? $this->returnLine($stop) : null)->afterCommit();
        }
    }
}
