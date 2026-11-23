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
 * The area manager on the round sees the cooks' and the office's pins — the
 * owner's decision; nobody else does (CLAUDE.md).
 */
class OfficeRounds
{
    /** Makes or refreshes the stops for this drop and area manager (who has which boxes may change until he leaves). */
    public function plan(OfficeDrop $drop, User $shahidar): Collection
    {
        return DB::transaction(function () use ($drop, $shahidar) {
            $orders = $drop->orders()->where('shahidar_id', $shahidar->id)
                ->whereIn('status', [OrderStatus::Booked, OrderStatus::PickedUp, OrderStatus::Delivered])->get();
            $stops = RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->lockForUpdate()->get()->keyBy('kitchen_id');
            $first = $stops->first();
            $started = $first?->started_at;

            foreach ($orders->groupBy('kitchen_id') as $kitchenId => $boxes) {
                $stop = $stops[$kitchenId] ?? new RoundStop(['office_drop_id' => $drop->id, 'shahidar_id' => $shahidar->id, 'kitchen_id' => $kitchenId,
                    'started_at' => $started, 'start_lat' => $first?->start_lat, 'start_lng' => $first?->start_lng]);
                $stop->boxes = $boxes->count();
                if (! $boxes->contains('status', OrderStatus::Booked)) {
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
    public function start(OfficeDrop $drop, User $shahidar, ?GeoPoint $here): Collection
    {
        $stops = $this->plan($drop, $shahidar);
        if ($stops->whereNull('picked_at')->isEmpty()) {
            throw ValidationException::withMessages(['round' => 'এই অফিসের তোলার মতো কোনো বক্স নেই।']);
        }
        $first = $stops->first()->started_at === null;
        RoundStop::whereIn('id', $stops->pluck('id'))->whereNull('started_at')
            ->update(['started_at' => now(), 'start_lat' => $here?->lat, 'start_lng' => $here?->lng]);
        $stops = $this->arrange($drop, $this->fresh($drop, $shahidar), $here, now());
        if ($first) {
            $this->tellNext($stops);
        }
        $this->tellSoon($stops);

        return $stops;
    }

    /** Called after a cook's boxes for the drop were picked up: on to the next house. */
    public function picked(OfficeDrop $drop, Kitchen $kitchen, User $shahidar): void
    {
        $stop = RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->where('kitchen_id', $kitchen->id)->first();
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

    /** "পরে আসব": she is not ready, go to the others first. */
    public function later(RoundStop $stop): Collection
    {
        abort_if($stop->picked_at !== null, 422, 'এই বাসা থেকে বক্স আগেই তোলা হয়েছে।');
        $stop->increment('later');
        $drop = $stop->drop;
        $stops = $this->fresh($drop, $stop->shahidar);
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

        $stops = $this->fresh($stop->drop, $stop->shahidar);
        if ($stop->started_at) {
            [$from, $at] = $this->whereNow($stops);
            $stops = $this->arrange($stop->drop, $stops, $from, $at);
            $this->tellNext($stops);
        }
        $name = $stop->kitchen->display_name;
        Push::toUser($stop->shahidar_id, $late ? "{$name}: আরও ১০ মিনিট লাগবে" : "{$name}: বক্স তৈরি আছে",
            $late ? 'আগে অন্য বাসায় যান, তালিকা নিজে থেকে বদলে গেছে।' : 'গেলেই বক্স পাবেন।',
            '/shahidar/round/'.$stop->office_drop_id, 'round-reply-'.$stop->id);

        return $stop->fresh();
    }

    /** Every minute: times move on, so the cooks whose turn is within 30 minutes are told. */
    public function tick(): int
    {
        $n = 0;
        RoundStop::query()->started()->open()
            ->whereHas('drop', fn ($q) => $q->whereDate('service_date', BookingPlanner::today())->whereNull('delivered_at'))
            ->get()->groupBy(fn (RoundStop $s) => $s->office_drop_id.'-'.$s->shahidar_id)
            ->each(function (Collection $group) use (&$n) {
                $first = $group->first();
                $stops = $this->fresh($first->drop, $first->shahidar);
                [$from, $at] = $this->whereNow($stops);
                $n += $this->tellSoon($this->arrange($first->drop, $stops, $from, $at));
            });

        return $n;
    }

    /** The round, for the area manager's screen. Pins included: he needs the way there. */
    public function forShahidar(OfficeDrop $drop, User $shahidar): array
    {
        $stops = $this->plan($drop, $shahidar);
        $company = $drop->company;
        $open = $stops->whereNull('picked_at')->values();

        return [
            'drop' => OfficeDrops::row($drop->load(['company.contact', 'company.area', 'stickers', 'orders' => fn ($q) => $q->where('shahidar_id', $shahidar->id)->with(['customer.user', 'kitchen.user', 'kitchen.area'])->orderBy('seq')])),
            'started' => $stops->first()?->started_at !== null,
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

    /** Today's not-yet-picked visits to this cook, for her app. No office or customer names. */
    public static function forCook(Kitchen $kitchen): Collection
    {
        return RoundStop::query()->open()->where('kitchen_id', $kitchen->id)
            ->whereHas('drop', fn ($q) => $q->whereDate('service_date', BookingPlanner::today()))
            ->with('drop')->orderBy('eta_at')->get()
            ->map(fn (RoundStop $s) => [
                'id' => $s->id,
                'meal_slot_label' => $s->drop->meal_slot->label(),
                'boxes' => $s->boxes,
                'started' => $s->started_at !== null,
                'eta' => $s->started_at && $s->eta_at ? ContactHours::clock($s->eta_at->timezone('Asia/Dhaka')->format('H:i')) : null,
                'coming_now' => $s->next_sent_at !== null,
                'reply' => $s->cook_reply,
                'ready_at' => $s->cook_ready_at ? ContactHours::clock($s->cook_ready_at->timezone('Asia/Dhaka')->format('H:i')) : null,
            ])->values();
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
            'contact' => ContactHours::card($k->user, [$s->drop->meal_slot], 'আসসালামু আলাইকুম, দাওয়াত থেকে এলাকা ম্যানেজার বলছি। অফিসের বক্স নিতে আসছি।'),
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
    private function fresh(OfficeDrop $drop, User $shahidar): Collection
    {
        return RoundStop::where('office_drop_id', $drop->id)->where('shahidar_id', $shahidar->id)->with(['kitchen.user', 'kitchen.area', 'drop'])->get();
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
            $best = $open->sortBy(function (RoundStop $s) use ($pos, $leftAt, $t, $office) {
                $arrive = $leftAt->copy()->addSeconds($this->travelSeconds($pos, $s->kitchen->location));
                $arrive = $arrive->lt($t) ? $t->copy() : $arrive;
                $ready = $s->cook_ready_at && $s->cook_ready_at->gt($arrive) ? $s->cook_ready_at : $arrive;
                // Without a known start, the house farthest from the office first, to end near it.
                $far = $pos === null && $office && $s->kitchen->location ? -$office->distanceTo($s->kitchen->location) : 0;

                return sprintf('%03d|%d|%012d|%012d|%08d', $s->later, $s->kitchen->location ? 0 : 1, $ready->getTimestamp(), (int) ($far + 1e9), $s->kitchen_id);
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
        $this->tell($next, 'next', 'এলাকা ম্যানেজার এখন আপনার কাছে আসছেন',
            'প্রায় '.Order::banglaDigits($minutes).' মিনিটে পৌঁছাবেন। অফিসের '.Order::banglaDigits($next->boxes).'টা বক্স তৈরি রাখুন।',
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
            $this->tell($stop, 'soon', 'এলাকা ম্যানেজার আসছেন: আনুমানিক '.$when,
                'অফিসের '.Order::banglaDigits($stop->boxes).'টা বক্স নিতে আসবেন। তৈরি রাখুন — দেরি হলে "আরও ১০ মিনিট লাগবে" চাপুন।',
                'আনুমানিক '.$when.'-এ');
            $n++;
        }

        return $n;
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
            SendRoundSms::dispatch($stop->id, $when)->afterCommit();
        }
    }
}
