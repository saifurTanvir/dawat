<?php

namespace App\Services\Ads;

use App\Enums\CustomerStatus;
use App\Enums\KitchenStatus;
use App\Models\AdLead;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\MessengerThread;
use App\Models\ShahidarApplication;
use App\Services\Geo\FocusSplit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * F93: the three Badda ads (published 21 Nov 2026, 15:50 Dhaka) — who came from each, on which
 * channel, whether we answered, and how many became cooks, customers or area managers.
 */
class BaddaAds
{
    public const LAUNCH = '2026-11-21 15:45:00';

    /**
     * key => label, Meta ad id, and text only that ad's greeting (Messenger/WhatsApp) or post (comments) has.
     * The cook greeting is shared with the rest-of-Dhaka cook ad, so cook chats without an ad id count only when they are in Badda.
     */
    public const ADS = [
        'cook' => [
            'label' => 'গৃহিণী', 'ad_id' => '120252639071060371',
            'greeting' => 'যোগ দিতে চাইলে শুধু আপনার নাম', 'shared' => true,
            'post' => 'শাহজাদপুরের গৃহিণীদের জন্য',
        ],
        'customer' => [
            'label' => 'গ্রাহক', 'ad_id' => '120252639235840371',
            'greeting' => 'বাড্ডায় ঘরের খাবার নিতে', 'shared' => false,
            'post' => 'বাড্ডায় অফিস করেন?',
        ],
        'manager' => [
            'label' => 'এলাকা ম্যানেজার', 'ad_id' => '120252639358330371',
            'greeting' => 'এলাকা ম্যানেজার হতে আগ্রহের জন্য', 'shared' => false,
            'post' => 'নিজের এলাকায় সম্মানজনক কাজ',
        ],
    ];

    private const BADDA_WORDS = '/বাড্ডা|badda|baddah|শাহজাদপুর|shahjadpur|shahzadpur|মেরুল|merul/iu';

    public static function launch(): Carbon
    {
        return Carbon::parse(self::LAUNCH, 'Asia/Dhaka')->utc();
    }

    /** Which ad (if any) a Messenger chat came from. */
    public function adOfThread(MessengerThread $thread, Collection $pageTexts, array $baddaAreaIds): ?string
    {
        $adId = (string) (($thread->facts ?? [])['ad_id'] ?? '');
        foreach (self::ADS as $key => $ad) {
            if ($adId !== '' && $adId === $ad['ad_id']) {
                return $key;
            }
        }
        if ($adId !== '') {
            return null; // another ad
        }
        foreach (self::ADS as $key => $ad) {
            if ($pageTexts->contains(fn ($t) => str_contains((string) $t, $ad['greeting']))) {
                if (! $ad['shared']) {
                    return $key;
                }
                $areaId = $thread->kitchen?->area_id ?? (($thread->facts ?? [])['area_id'] ?? null);
                $inBadda = ($areaId && in_array((int) $areaId, $baddaAreaIds, true))
                    || preg_match(self::BADDA_WORDS, (string) (($thread->facts ?? [])['area_text'] ?? '')) === 1;

                return $inBadda ? $key : null;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> per ad: counts by channel, answers, and results */
    public function report(): array
    {
        $since = self::launch();
        $badda = app(FocusSplit::class)->focusAreaIds();

        $threads = MessengerThread::query()->where('created_at', '>=', $since->copy()->subDay())
            ->where(fn ($q) => $q->where('last_in_at', '>=', $since)->orWhere('created_at', '>=', $since))
            ->with(['kitchen:id,area_id,status,location', 'messages' => fn ($q) => $q->select(['id', 'messenger_thread_id', 'direction', 'by', 'text', 'created_at'])])
            ->get();

        $byAd = array_fill_keys(array_keys(self::ADS), []);
        foreach ($threads as $t) {
            $page = $t->messages->where('direction', 'out')->where('by', 'page')->pluck('text');
            $key = $this->adOfThread($t, $page, $badda);
            if ($key) {
                $byAd[$key][] = $t;
            }
        }

        $leads = AdLead::query()->where('first_at', '>=', $since)->get()->groupBy('ad');
        $kitchens = Kitchen::query()->where('created_at', '>=', $since)->whereIn('area_id', $badda)
            ->whereIn('status', [KitchenStatus::Approved, KitchenStatus::Pending])->get(['id', 'status', 'location']);
        $customers = Customer::query()->where('created_at', '>=', $since)->where('status', '!=', CustomerStatus::Stopped)->get(['id', 'area_id']);
        $customerCalls = CallRequest::query()->where('created_at', '>=', $since)->where('reason', 'like', 'নতুন গ্রাহক%')->count();
        $apps = ShahidarApplication::query()->where('created_at', '>=', $since)->with('areas:id')->get(['id']);

        $out = [];
        foreach (self::ADS as $key => $ad) {
            $th = collect($byAd[$key]);
            $answered = $th->filter(fn ($t) => $t->messages->contains(fn ($m) => $m->direction === 'out' && in_array($m->by, ['bot', 'admin'], true)));
            $wa = $leads->get($key, collect())->where('channel', 'whatsapp');
            $cm = $leads->get($key, collect())->where('channel', 'comment');

            $row = [
                'label' => $ad['label'],
                'messenger' => $th->count(),
                'messenger_answered' => $answered->count(),
                'messenger_waiting' => $th->filter(fn ($t) => $t->needs_human)->count(),
                'whatsapp' => $wa->count(),
                'whatsapp_answered' => $wa->whereNotNull('replied_at')->count(),
                'comments' => $cm->count(),
                'comments_answered' => $cm->whereNotNull('replied_at')->count(),
            ];
            $row['total'] = $row['messenger'] + $row['whatsapp'] + $row['comments'];
            $row['answered'] = $row['messenger_answered'] + $row['whatsapp_answered'] + $row['comments_answered'];

            $row['results'] = match ($key) {
                'cook' => [
                    ['নাম-নম্বর দিয়েছেন (Messenger)', $th->filter(fn ($t) => filled(($t->facts ?? [])['phone'] ?? null))->count()],
                    ['রাঁধুনি হিসেবে যোগ হয়েছেন (বাড্ডা)', $kitchens->count()],
                    ['অনুমোদিত', $kitchens->where('status', KitchenStatus::Approved)->count()],
                    ['বাসার পিন দিয়েছেন', $kitchens->filter(fn ($k) => $k->location !== null)->count()],
                ],
                'customer' => [
                    ['ঠিকানা দিয়েছেন (Messenger)', $th->filter(fn ($t) => filled(($t->facts ?? [])['customer_address'] ?? null))->count()],
                    ['কল-লিস্টে নতুন গ্রাহক', $customerCalls],
                    ['গ্রাহক হিসেবে যোগ হয়েছেন (বাড্ডা)', $customers->whereIn('area_id', $badda)->count()],
                    ['গ্রাহক যোগ হয়েছেন (সব এলাকা)', $customers->count()],
                ],
                'manager' => [
                    ['নাম-নম্বর দিয়েছেন (Messenger)', $th->filter(fn ($t) => filled(($t->facts ?? [])['phone'] ?? null))->count()],
                    ['আবেদন করেছেন (সব এলাকা)', $apps->count()],
                    ['আবেদন — বাড্ডায় কাজ করতে চান', $apps->filter(fn ($a) => $a->areas->pluck('id')->intersect($badda)->isNotEmpty())->count()],
                ],
            };
            $row['recent'] = $th->sortByDesc('last_in_at')->take(8)->map(fn ($t) => [
                'channel' => 'Messenger', 'name' => $t->name ?: 'নাম জানা নেই', 'phone' => ($t->facts ?? [])['phone'] ?? null,
                'at' => $t->last_in_at, 'answered' => $answered->contains('id', $t->id), 'thread' => $t->id,
            ])->concat($wa->concat($cm)->sortByDesc('first_at')->take(8)->map(fn ($l) => [
                'channel' => $l->channel === 'whatsapp' ? 'WhatsApp' : 'কমেন্ট', 'name' => $l->name ?: '—', 'phone' => $l->phone,
                'at' => $l->first_at, 'answered' => $l->replied_at !== null, 'thread' => null,
            ]))->sortByDesc('at')->take(10)->values()->all();

            $out[$key] = $row;
        }

        return $out;
    }

    /** WhatsApp lead noted by whoever answers WhatsApp (the hourly pass). */
    public function noteWhatsApp(string $ad, string $phone, ?string $name, ?string $text, bool $replied, ?Carbon $at = null): AdLead
    {
        $lead = AdLead::query()->firstOrNew(['channel' => 'whatsapp', 'key' => $phone]);
        $lead->fill([
            'ad' => $ad, 'phone' => $phone,
            'name' => $name ?: $lead->name,
            'text' => $text ? mb_substr($text, 0, 500) : $lead->text,
            'first_at' => $lead->first_at ?? $at ?? now(),
        ]);
        if ($replied && ! $lead->replied_at) {
            $lead->replied_at = now();
        }
        $lead->save();

        return $lead;
    }
}
