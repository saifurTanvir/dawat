<?php

namespace App\Services\Messenger;

use App\Filament\Actions\QuickAddActions;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\MessengerThread;
use App\Models\OpsHeartbeat;
use App\Models\PinLink;
use App\Models\User;
use App\Services\Ai\AssistantUnavailableException;
use App\Services\Kitchens\PinLinks;
use App\Support\Geo\GeoPoint;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public const WELCOME = [
        'assalamu alaikum apu 😊 Dawat theke bolchi',
        'apni basay ranna kore ashepasher manusher kache bikri korte parben — amader delivery bhai (shahidar) basar gate theke box niye jaben, box Dawat dibe',
        'join korte apnar nam, elaka ar mobile number ta likhe din — ami nijei add kore dibo',
    ];

    /** @return int messages sent */
    public function answer(MessengerThread $thread): int
    {
        if (! $thread->botMayAnswer()) {
            return 0;
        }
        $this->graph->typing($thread->psid);

        $extra = [];
        $location = $this->sharedLocation($thread);

        try {
            $out = $this->brain->think($thread);
        } catch (AssistantUnavailableException $e) {
            Log::warning('Messenger brain unavailable', ['thread' => $thread->id, 'error' => $e->getMessage()]);
            $out = null;
        }

        if ($out === null) {
            // No AI: greet once with the standard lines, and let an admin take it from there.
            $first = ! $thread->messages()->where('direction', 'out')->exists();
            $this->flag($thread, 'বট উত্তর লিখতে পারেনি (AI চালু নেই) — নিজে উত্তর দিন');
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
        $thread->facts = $facts;
        $thread->save();

        $replies = $out['replies'];
        if ($out['needs_human']) {
            $this->flag($thread, $out['human_reason'] ?: 'বট বলছে একবার দেখুন');
        }

        if (! $thread->kitchen_id && ($facts['cook'] ?? false) && filled($facts['name'] ?? null) && filled($facts['phone'] ?? null)) {
            if (filled($facts['area_id'] ?? null)) {
                return $this->addCook($thread, $extra) ?? $replies;
            }
            if (filled($facts['area_text'] ?? null)) {
                $this->flag($thread, 'এলাকা তালিকায় নেই: "'.$facts['area_text'].'" — এলাকা যোগ করে রান্নাঘর যোগ করুন');
            }
        }
        if ($badPhone && ! $thread->kitchen_id) {
            $replies[] = 'apu mobile number ta 01 diye 11 digit e likhun 🙏';
        }

        $kitchen = $thread->kitchen;
        if ($kitchen && $out['plates'] !== null && $kitchen->daily_plates !== $out['plates']) {
            $kitchen->forceFill(['daily_plates' => $out['plates']])->save();
        }
        if ($kitchen && ! $kitchen->location && $out['send_pin_link']) {
            $extra[] = 'apnar location er link (basay boshe khulun, location keu dekhbe na): '.PinLink::forKitchen($kitchen)->url();
        }

        if ($out['wants_call']) {
            $this->callList($thread, $out['call_reason']);
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

            return ['apu ei number e age thekei ekta Dawat rannaghor ache 🙂', 'amader team check kore apnake ekhanei janabe 🙏'];
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
                $thread->forceFill(['kitchen_id' => $kitchen->id])->save();

                return $kitchen;
            });
        } catch (ValidationException $e) {
            $this->flag($thread, 'রান্নাঘর যোগ হয়নি: '.collect($e->errors())->flatten()->implode(' '));

            return null;
        }

        $area = Area::find($facts['area_id'])?->name;
        $extra[] = 'basay boshe ei link khulun, location keu dekhbe na: '.PinLink::forKitchen($kitchen)->url();
        $extra[] = 'link e na hole ekhane 📎 → Location pathan';
        $extra[] = 'ar: dine shorbochcho 2 plate — apni 1 plate dite parben na 2?';

        return [
            'dhonnobad apu 😊 apnake '.($area ?: 'apnar elaka').' e add kore approve kore dilam ✅',
            'ekhon shudhu basar location ta lagbe — ashepasher 1 km er customer apnar sathe jurbe, ar amader delivery bhai (shahidar) basar gate theke box nibe (box Dawat dibe)',
        ];
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

        return [$result === 'saved' ? 'location peyechi, boshiye dilam ✅ dhonnobad apu 😊' : 'location peyechi ✅ amader team ekbar mile dekhe nibe 🙏'];
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
            'channel' => 'messenger', 'sent' => $sent, 'pending' => MessengerThread::query()->waiting()->count(),
            'note' => 'সার্ভার (Messenger API)', 'problem' => (bool) $thread->send_error,
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
