<?php

namespace App\Services\Messenger;

use App\Enums\KitchenStatus;
use App\Filament\Actions\QuickAddActions;
use App\Jobs\AnswerMessenger;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\MessengerThread;
use App\Models\OpsHeartbeat;
use App\Models\PinLink;
use App\Models\User;
use App\Services\Ai\AssistantUnavailableException;
use App\Services\Kitchens\PinLinks;
use App\Support\Geo\Geocoder;
use App\Support\Geo\GeoPoint;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F80: answers one Messenger chat. The Brain writes the words and reads out
 * name / area / number; this class does the real work the browser loop did by
 * hand: adds and approves the cook, sends her own pin link (only ever to the
 * chat she was added from), saves her plates, puts call requests on the call
 * list and flags chats an admin must see.
 */
class Agent
{
    public function __construct(private readonly Graph $graph, private readonly Brain $brain) {}

    public const AI_FAILED = 'বট উত্তর লিখতে পারেনি (AI চালু নেই) — নিজে উত্তর দিন';

    public const NO_AREA = 'এলাকা তালিকায় নেই';

    public const WELCOME = [
        'assalamu alaikum 😊 Dawat theke bolchi',
        'apni basay ranna kore ashepasher manusher kache bikri korte parben — amader elaka manager basar gate theke box niye jaben, box Dawat dibe',
        'join korte apnar nam, elaka ar mobile number ta likhe din — ami nijei add kore dibo',
    ];

    /** "ok", "thanks", a like or a sticker: a smile back, no AI needed. */
    public const SMALL_TALK = '/^\s*(ok+|okay|oke|k|thanks?|thank you|tnx|thx|dhonnobad|donnobad|ধন্যবাদ|জি|জ্বি|ji|jee|hmm+|accha|আচ্ছা|ঠিক আছে|thik ase|thik ache|alhamdulillah|আলহামদুলিল্লাহ)?(\s*(apu|apa|vai|bhai|আপু|ভাই))?[\s!.।\p{So}\p{Sk}\x{200d}\x{fe0f}]*$/iu';

    /**
     * @param  bool  $final  false while the job may still retry: a busy AI then throws instead of falling back
     * @return int messages sent
     */
    public function answer(MessengerThread $thread, bool $final = true): int
    {
        if (! $thread->botMayAnswer()) {
            return 0;
        }
        $this->graph->markSeen($thread->psid);

        $extra = [];
        $location = $this->sharedLocation($thread);

        if (! $location && $this->onlySmallTalk($thread)) {
            return $this->send($thread, ['😊']);
        }
        $this->graph->typing($thread->psid);

        $why = null;
        try {
            $out = $this->brain->think($thread);
        } catch (AssistantUnavailableException $e) {
            $why = $e->getMessage();
            Log::warning('Messenger brain unavailable', ['thread' => $thread->id, 'error' => $e->getMessage(), 'final' => $final]);
            if ($e->busy && ! $final) {
                throw $e; // the job tries again in a little while
            }
            $out = null;
        }

        if ($out === null) {
            // No AI even after retrying: greet once with the standard lines; the chat is tried again later.
            $first = ! $thread->messages()->where('direction', 'out')->exists();
            $this->flag($thread, self::AI_FAILED.($why ? " ({$why})" : ''));
            $replies = $first ? self::WELCOME : [];
        } else {
            $replies = $this->act($thread, $out, $extra);
        }

        if ($location) {
            $replies = [...$replies, ...$this->savePin($thread, $location)];
        }

        return $this->send($thread, [...$replies, ...$extra]);
    }

    /**
     * Does what the facts call for; returns the messages to send.
     *
     * @param  list<string>  $extra  messages the server adds (links) after the replies
     * @return list<string>
     */
    private function act(MessengerThread $thread, array $out, array &$extra): array
    {
        $facts = $thread->facts ?? [];
        foreach (['name', 'area_text', 'plates'] as $key) {
            if ($out[$key] !== null) {
                $facts[$key] = $out[$key];
            }
        }
        if ($out['area_id'] && Area::active()->whereKey($out['area_id'])->exists()) {
            $facts['area_id'] = $out['area_id'];
        } elseif (($out['area_place'] ?? null) && empty($facts['area_id']) && ($out['cook'] || ($facts['cook'] ?? false))) {
            // Owner's rule: nobody is turned away — a place not in the list becomes a new area.
            if ($area = $this->areaFor($out['area_place'], $out['area_name_bn'] ?? null, $out['area_text'] ?? $out['area_place'])) {
                $facts['area_id'] = $area->id;
            }
        }
        $badPhone = false;
        if ($out['phone'] !== null) {
            if ($phone = PhoneNumber::normalize($out['phone'])) {
                $facts['phone'] = $phone;
            } else {
                $badPhone = true;
            }
        }
        if ($out['cook']) {
            $facts['cook'] = true;
        }
        if ($out['customer'] ?? false) {
            $facts['customer'] = true;
            if (filled($out['home_address'] ?? null)) {
                $facts['customer_address'] = $out['home_address'];
            }
        }
        $thread->facts = $facts;
        $thread->save();

        $replies = $out['replies'];
        if ($out['needs_human']) {
            $this->flag($thread, $out['human_reason'] ?: 'বট বলছে একবার দেখুন');
        }

        // A number that already has a kitchen is told so once; after that the Brain answers her normally.
        $alreadyTold = ($facts['existing_kitchen_phone'] ?? null) === ($facts['phone'] ?? false);
        if (! $thread->kitchen_id && ! $alreadyTold && ($facts['cook'] ?? false) && filled($facts['name'] ?? null) && filled($facts['phone'] ?? null)) {
            if (filled($facts['area_id'] ?? null)) {
                return $this->addCook($thread, $extra) ?? $replies;
            }
            if (filled($facts['area_text'] ?? null)) {
                $this->flag($thread, self::NO_AREA.': "'.$facts['area_text'].'" — এলাকা যোগ করে রান্নাঘর যোগ করুন');
            }
        }
        if ($badPhone && ! $thread->kitchen_id) {
            $replies[] = 'mobile number ta 01 diye 11 digit e likhun 🙏';
        }

        $kitchen = $thread->kitchen;
        // Her written address helps the shahidar find the gate; the pin still comes from her link.
        if ($kitchen && ($out['home_address'] ?? null) && blank($kitchen->pickup_address)) {
            $kitchen->forceFill(['pickup_address' => $out['home_address']])->save();
        }
        if ($kitchen && $out['plates'] !== null && $kitchen->daily_plates !== $out['plates']) {
            $kitchen->forceFill(['daily_plates' => $out['plates']])->save();
        }
        if ($kitchen && ! $kitchen->location && $out['send_pin_link']) {
            $extra[] = 'apnar location er link (basay boshe khulun, location keu dekhbe na): '.PinLink::forKitchen($kitchen)->url();
        }

        if ($out['wants_call']) {
            $this->callList($thread, $out['call_reason']);
        }
        // F89: a would-be customer with name, number and address goes on the call list once, to be added as a customer.
        if (($facts['customer'] ?? false) && ! ($facts['cook'] ?? false) && ! ($facts['customer_listed'] ?? false)
            && filled($facts['phone'] ?? null) && filled($facts['customer_address'] ?? null)) {
            $this->callList($thread, 'নতুন গ্রাহক — নিয়মিত খাবার নিতে চান। ঠিকানা: '.$facts['customer_address'].($facts['area_text'] ?? null ? ' ('.$facts['area_text'].')' : ''));
            $thread->facts = [...$thread->facts, 'customer_listed' => true];
            $thread->save();
        }

        return $replies;
    }

    /** Adds and approves her, then sends her own pin link. Null when it could not (the chat is flagged). */
    private function addCook(MessengerThread $thread, array &$extra): ?array
    {
        $facts = $thread->facts;
        $existing = User::where('phone', $facts['phone'])->first()?->kitchen;
        if ($existing) {
            $this->flag($thread, "এই নম্বরে ({$facts['phone']}) আগে থেকেই রান্নাঘর আছে: #{$existing->id} {$existing->display_name} — মিলিয়ে দেখুন");
            // Remember it so the same two lines are not sent again on every message.
            $thread->facts = [...$facts, 'existing_kitchen_phone' => $facts['phone']];
            $thread->save();

            return ['ei number diye apni age thekei Dawat e join kora ache ✅ notun kore add korte hobe na', 'amader team mile dekhe ekhanei janabe 🙏 ar kichu jante chaile likhun'];
        }

        try {
            $kitchen = DB::transaction(function () use ($facts, $thread) {
                $kitchen = QuickAddActions::createCook([
                    'phone' => $facts['phone'],
                    'name' => $facts['name'],
                    'area_id' => $facts['area_id'],
                    'display_name' => mb_substr($facts['name'], 0, 45).'-এর রান্নাঘর',
                    'pickup_address' => null,
                ]);
                if ($approver = self::approver()) {
                    $kitchen->approve($approver);
                }
                // Owner's rule: 1 plate a day until she says otherwise.
                $kitchen->forceFill(['daily_plates' => $kitchen->daily_plates ?? 1])->save();
                $thread->forceFill(['kitchen_id' => $kitchen->id])->save();

                return $kitchen;
            });
        } catch (ValidationException $e) {
            $this->flag($thread, 'রান্নাঘর যোগ হয়নি: '.collect($e->errors())->flatten()->implode(' '));

            return null;
        }

        $area = $facts['area_text'] ?? Area::find($facts['area_id'])?->slug;
        $extra[] = 'basay boshe ei link khulun, location keu dekhbe na: '.PinLink::forKitchen($kitchen)->url();
        $extra[] = 'link e na hole ekhane 📎 → Location pathan';
        $extra[] = 'ar: shuru te dine 1 plate (ek joner purno khabar) nebo 😊';

        return [
            'dhonnobad 😊 apnake '.($area ?: 'apnar elaka').' e add kore approve kore dilam ✅',
            'ekhon shudhu basar location ta lagbe — ashepasher 1 km er customer apnar sathe jurbe, ar amader elaka manager basar gate theke box nibe (box Dawat dibe)',
        ];
    }

    /**
     * Chats flagged because the number she gave already has a kitchen: join
     * the chat to that kitchen (same number, so it is hers) and tell her once,
     * with her own location link if her pin is still missing.
     *
     * @return int chats joined
     */
    public function linkExisting(): int
    {
        $n = 0;
        $threads = MessengerThread::query()->whereNull('kitchen_id')->where('needs_human', true)->get()
            ->filter(fn (MessengerThread $t) => filled($t->fact('phone')));
        foreach ($threads as $t) {
            $kitchen = User::where('phone', $t->fact('phone'))->first()?->kitchen;
            // A page or business writing for someone else is left for a person.
            if (! $kitchen || preg_match('/\b(bites?|food|foods|kitchen|shop|store|business|restaurant|catering)\b/i', (string) $t->name)) {
                continue;
            }
            $t->forceFill(['kitchen_id' => $kitchen->id, 'needs_human' => false, 'human_reason' => null])->save();
            $messages = ['mile dekhlam ✅ ei number e apnar rannaghor "'.$kitchen->display_name.'" age thekei ache — notun kore kichu korte hobe na 😊'];
            if (! $kitchen->location) {
                $messages[] = 'shudhu basar location ta baki — basay boshe ei link khulun, location keu dekhbe na: '.PinLink::forKitchen($kitchen)->url();
                $messages[] = 'link e na hole ekhane 📎 → Location pathan';
            }
            if (! $kitchen->daily_plates) {
                $messages[] = 'ar: shuru te dine 1 plate (ek joner purno khabar) nebo 😊';
            }
            $this->send($t, $messages);
            $n++;
        }

        return $n;
    }

    /**
     * Cooks still without a home pin who wrote to us within the last 7 days
     * (Meta allows up to 7 days for chats started from an ad; others just fail): join the chat to her kitchen by her number if needed, then send
     * her own location link (at most once every 12 hours).
     *
     * @return int reminders sent
     */
    /**
     * F82: after the owner adds answers to "বটের জ্ঞান", the chats that were sent to
     * "মানুষ লাগবে" (inside Meta's 7-day window) are answered again from the new
     * facts. Ones the facts still do not cover get flagged again by the Brain.
     */
    /** @param  list<string>  $words  also chats where the person wrote any of these (e.g. "pitha", "আচার") */
    public function reanswerFlagged(array $words = []): int
    {
        $words = array_values(array_filter(array_map(fn ($w) => trim((string) $w), $words), fn ($w) => mb_strlen($w) >= 2));
        $n = 0;
        MessengerThread::query()->where('bot_off', false)
            ->where(fn ($q) => $q->where('needs_human', true)->when($words, fn ($q) => $q->orWhereHas('messages', fn ($m) => $m->where('direction', 'in')
                ->where(fn ($m) => collect($words)->each(fn ($w) => $m->orWhere('text', 'like', '%'.addcslashes($w, '%_\\').'%'))))))
            ->whereNull('send_error')->where('last_in_at', '>', now()->subHours(166))
            ->orderBy('last_in_at')->get()
            ->each(function (MessengerThread $t) use (&$n) {
                $t->forceFill([
                    'facts' => [...($t->facts ?? []), 'followup_at' => now()->toIso8601String()],
                    'needs_human' => false, 'human_reason' => null, 'paused_until' => null,
                ])->save();
                $last = (int) $t->messages()->where('direction', 'in')->max('id');
                AnswerMessenger::dispatch($t->id, $last, force: true)->delay(now()->addSeconds(3 * $n++));
            });

        return $n;
    }

    /** Owner, 21 Nov: cooks found the 12-hourly reminders too many — once every 2–3 days. */
    public const PIN_REMIND_GAP_HOURS = 60;

    public function remindPins(): int
    {
        $n = 0;
        $threads = MessengerThread::query()->where('last_in_at', '>', now()->subHours(166))->where('bot_off', false)->get();
        foreach ($threads as $t) {
            $kitchen = $t->kitchen;
            if (! $kitchen && filled($t->fact('phone')) && ! preg_match('/\b(bites?|food|foods|kitchen|shop|store|business|restaurant|catering)\b/i', (string) $t->name)) {
                $kitchen = User::where('phone', $t->fact('phone'))->first()?->kitchen;
                if ($kitchen) {
                    $t->forceFill(['kitchen_id' => $kitchen->id])->save();
                }
            }
            if (! $kitchen || $kitchen->location || ! in_array($kitchen->status, [KitchenStatus::Approved, KitchenStatus::Pending], true)) {
                continue;
            }
            $last = $t->fact('pin_reminded_at');
            if ($last && now()->diffInHours(Carbon::parse($last), true) < self::PIN_REMIND_GAP_HOURS) {
                continue;
            }
            $this->graph->markSeen($t->psid);
            $sent = $this->send($t, [
                'apnar basar location ta ekhono baki 🙏 location chara kache er customer der sathe jora jay na, elaka manager o gate khuje pay na',
                'basay boshe ei link khulun — location keu dekhbe na: '.PinLink::forKitchen($kitchen)->url(),
                'link e na hole ekhane 📎 → Location → "Send current location" pathan, ba basar thikana likhe din — ami nijei boshiye dibo ✅',
            ]);
            if ($sent) {
                $t->facts = [...($t->facts ?? []), 'pin_reminded_at' => now()->toIso8601String()];
                $t->save();
                $n++;
            }
        }

        return $n;
    }

    /**
     * An area for a place that is not in the list: an existing area whose centre is within
     * 1.5 km of the place, or a new one around it. Null when the map does not know the place.
     */
    private function areaFor(string $place, ?string $nameBn, string $text): ?Area
    {
        $hit = app(Geocoder::class)->search(str_contains(strtolower($place), 'bangladesh') ? $place : $place.', Bangladesh')[0] ?? null;
        if (! $hit) {
            return null;
        }
        $at = GeoPoint::make($hit['lat'], $hit['lng']);
        $near = Area::active()->get()->filter(fn (Area $a) => $a->center && $a->center->distanceTo($at) <= 1500)
            ->sortBy(fn (Area $a) => $a->center->distanceTo($at))->first();
        if ($near) {
            return $near;
        }
        $slug = Str::slug(Str::before($place, ',')) ?: 'area';
        $base = $slug;
        for ($i = 2; Area::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $fee = (int) (Area::where('is_active', true)->selectRaw('delivery_fee_paisa, COUNT(*) AS n')
            ->groupBy('delivery_fee_paisa')->orderByDesc('n')->value('delivery_fee_paisa') ?? 0);

        Log::info('Messenger bot added an area', ['slug' => $slug, 'place' => $place]);

        return Area::create([
            'slug' => $slug,
            'name' => $nameBn ?: Str::title(Str::before($text, ',')),
            'center' => $at,
            'radius_m' => 1500,
            'delivery_fee_paisa' => $fee,
            'is_active' => true,
            'sort_order' => (int) Area::max('sort_order') + 1,
        ]);
    }

    /** Everything they wrote since our last answer is small talk (and we have talked before). */
    private function onlySmallTalk(MessengerThread $thread): bool
    {
        $lastOut = $thread->messages()->where('direction', 'out')->max('id');
        if (! $lastOut) {
            return false;
        }
        $new = $thread->messages()->where('direction', 'in')->where('id', '>', $lastOut)->get();
        if ($new->isEmpty()) {
            return false;
        }

        return $new->every(function ($m) {
            $text = trim((string) $m->text);
            if ($text === '') {
                return filled($m->attachments) && collect($m->attachments)->every(fn ($a) => filled($a['payload']['sticker_id'] ?? null));
            }

            return (bool) preg_match(self::SMALL_TALK, $text);
        });
    }

    /** Coordinates from a location they shared in the chat, if the newest messages have one. */
    private function sharedLocation(MessengerThread $thread): ?GeoPoint
    {
        $last = $thread->messages()->where('direction', 'in')->latest('id')->limit(3)->get();
        foreach ($last as $m) {
            foreach ($m->attachments ?? [] as $a) {
                $c = $a['payload']['coordinates'] ?? null;
                if (($a['type'] ?? null) === 'location' && isset($c['lat'], $c['long']) && empty($a['used'])) {
                    $m->attachments = collect($m->attachments)->map(fn ($x) => $x === $a ? [...$x, 'used' => true] : $x)->all();
                    $m->save();

                    return GeoPoint::make((float) $c['lat'], (float) $c['long']);
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function savePin(MessengerThread $thread, GeoPoint $at): array
    {
        $kitchen = $thread->kitchen;
        if (! $kitchen) {
            $this->flag($thread, 'রান্নাঘর যোগ হওয়ার আগেই লোকেশন পাঠিয়েছেন — যোগ হলে পিন বসান');

            return [];
        }
        $result = app(PinLinks::class)->submit(PinLink::forKitchen($kitchen), $at, 'map', null);

        return [$result === 'saved' ? 'location peyechi, boshiye dilam ✅ dhonnobad 😊' : 'location peyechi ✅ amader team ekbar mile dekhe nibe 🙏'];
    }

    private function callList(MessengerThread $thread, ?string $reason): void
    {
        $phone = $thread->fact('phone') ?? $thread->kitchen?->user?->phone;
        if (! $phone) {
            $this->flag($thread, 'ফোনে কথা বলতে চান, নম্বর দেননি');

            return;
        }
        if ($thread->call_request_id && $thread->callRequest?->status->value !== 'done') {
            return;
        }
        $call = CallRequest::create([
            'phone' => $phone, 'name' => $thread->fact('name') ?? $thread->name,
            'reason' => ($reason ?: 'ফোনে কথা বলতে চান').' (Messenger)', 'source' => 'messenger',
        ]);
        $thread->forceFill(['call_request_id' => $call->id])->save();
    }

    private function flag(MessengerThread $thread, string $reason): void
    {
        $thread->forceFill(['needs_human' => true, 'human_reason' => mb_substr($reason, 0, 255)])->save();
    }

    /** @param  list<string>  $messages */
    private function send(MessengerThread $thread, array $messages): int
    {
        $sent = 0;
        foreach ($messages as $text) {
            $text = self::clean($text);
            if ($text === '') {
                continue;
            }
            if ($error = $this->graph->send($thread->psid, $text)) {
                $thread->forceFill(['send_error' => $error])->save();
                break;
            }
            $thread->messages()->create(['direction' => 'out', 'by' => 'bot', 'text' => $text]);
            $sent++;
        }
        if ($sent) {
            $thread->forceFill(['last_out_at' => now(), 'send_error' => null])->save();
        }
        OpsHeartbeat::create([
            // One person answered (however many lines); waiting = chats Meta still lets us answer.
            'channel' => 'messenger', 'sent' => $sent ? 1 : 0, 'pending' => MessengerThread::query()->answerable()->count(),
            'note' => 'সার্ভার (Messenger API)', 'problem' => filled($thread->send_error) && ! $thread->unreachable(),
        ]);

        return $sent;
    }

    /** No links but ours and our pin links, no WhatsApp. */
    public static function clean(string $text): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'dawat.cloud';
        $text = preg_replace_callback('~(https?://\S+|www\.\S+|wa\.me/\S+)~i', function ($m) use ($host) {
            $h = parse_url(str_starts_with(strtolower($m[1]), 'http') ? $m[1] : 'https://'.$m[1], PHP_URL_HOST);

            return in_array($h, [$host, 'dawat.cloud', 'www.dawat.cloud'], true) ? $m[1] : '';
        }, $text);

        return trim(preg_replace('/[ \t]{2,}/', ' ', $text));
    }

    /** Kitchens the bot approves are recorded under the first admin. */
    public static function approver(): ?User
    {
        return User::role(Roles::ADMIN)->orderBy('id')->first();
    }
}
