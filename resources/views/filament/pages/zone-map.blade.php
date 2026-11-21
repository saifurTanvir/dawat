@php($bn = fn ($n) => \App\Services\Geo\Zones::bn($n))
<x-filament-panels::page>
    {{-- Inside the page root: a Livewire component must have a single root element. --}}
    <style>
        .dz th, .dz td { padding: 6px 10px 6px 0; text-align: left; vertical-align: top; }
        .dz .r { text-align: right; }
        .dz tbody tr { border-top: 1px solid rgb(229 231 235); }
        .dz .muted { color: rgb(107 114 128); font-size: .75rem; }
        .dz-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; }
        .dz-stat { border: 1px solid rgb(229 231 235); border-radius: .75rem; padding: .75rem 1rem; }
        .dz-stat b { display: block; font-size: 1.5rem; }
        .dz-badge { display: inline-block; border-radius: 999px; padding: 1px 8px; font-size: .75rem; font-weight: 600; }
        .dz-ready { background: #dcfce7; color: #166534; } .dz-some { background: #fef3c7; color: #92400e; } .dz-none { background: #f3f4f6; color: #6b7280; }
        .dz-map { height: 28rem; width: 100%; border-radius: .75rem; overflow: hidden; border: 1px solid rgb(229 231 235); position: relative; z-index: 0; isolation: isolate; } /* the map stays under the panel's top bar */
        .dark .dz-stat, .dark .dz-map { border-color: rgba(255,255,255,.1); }
        /* Phones: each zone is a small card instead of a 7-column table. */
        .dz-cards { display: none; }
        .dz-card { border-top: 1px solid rgb(229 231 235); padding: .75rem 0; }
        .dz-card:first-child { border-top: 0; padding-top: 0; }
        .dark .dz-card, .dark .dz tbody tr { border-color: rgba(255,255,255,.1); }
        .dz-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
        .dz-nums { display: grid; grid-template-columns: repeat(4, 1fr); gap: .25rem; margin-top: .5rem; text-align: center; }
        .dz-nums div { border-radius: .5rem; background: rgba(156,163,175,.12); padding: .35rem .25rem; }
        .dz-nums b { display: block; font-size: 1.05rem; }
        .dz-nums span { font-size: .7rem; color: rgb(107 114 128); }
        .dz-lz { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .75rem; }
        .dz-lz-card { border: 1px solid rgb(229 231 235); border-radius: .75rem; padding: .75rem 1rem; }
        .dz-lz-card.on { border-color: #16a34a; box-shadow: 0 0 0 1px #16a34a; }
        .dark .dz-lz-card { border-color: rgba(255,255,255,.1); }
        .dz-lz-bar { height: 6px; border-radius: 999px; background: rgba(156,163,175,.25); margin: .4rem 0 .6rem; overflow: hidden; }
        .dz-lz-bar i { display: block; height: 100%; background: #16a34a; }
        .dz-lz-nums { display: grid; grid-template-columns: repeat(4, 1fr); gap: .25rem; text-align: center; }
        .dz-lz-nums button { border-radius: .5rem; background: rgba(156,163,175,.12); padding: .35rem .25rem; cursor: pointer; }
        .dz-lz-nums button.sel { background: #dcfce7; color: #166534; }
        .dz-lz-nums b { display: block; font-size: 1.1rem; }
        .dz-lz-nums span { font-size: .7rem; color: rgb(107 114 128); }
        @media (max-width: 640px) {
            .dz-wide { display: none; }
            .dz-cards { display: block; }
            .dz-map { height: 20rem; }
            .dz-stat b { font-size: 1.25rem; }
        }
    </style>

    <div class="dz-stats">
        <div class="dz-stat"><span class="muted">মোট জোন (১ কিমি)</span><b>{{ $bn($totals['zones']) }}</b></div>
        <div class="dz-stat"><span class="muted">গৃহিণী আছে এমন জোন</span><b>{{ $bn($totals['zones_with_cooks']) }}</b></div>
        <div class="dz-stat"><span class="muted">৩+ গৃহিণী (কর্পোরেটের জন্য তৈরি)</span><b>{{ $bn($totals['zones_ready']) }}</b></div>
        <div class="dz-stat"><span class="muted">গৃহিণী (অনুমোদিত / অপেক্ষায়)</span><b>{{ $bn($totals['cooks']) }} / {{ $bn($totals['pending']) }}</b></div>
        <div class="dz-stat"><span class="muted">গ্রাহক</span><b>{{ $bn($totals['customers']) }}</b></div>
        <div class="dz-stat"><span class="muted">শাহিদার</span><b>{{ $bn($totals['shahidars']) }}</b></div>
    </div>


    {{-- F84: zones we may start operations in. Counted by the cook's chosen area, so cooks without a pin count too. --}}
    @php($whichLabel = ['all' => 'সব অনুমোদিত গৃহিণী', 'pin' => 'লোকেশন আছে', 'nopin' => 'লোকেশন নেই', 'pending' => 'অপেক্ষায়'])
    <x-filament::section heading="অপারেশনের জোন" description="কয়েকটা পাশাপাশি এলাকা মিলে একটা জোন। শুরু করতে একটা জোনে ৭০–৮০ জন গৃহিণী লাগবে। সংখ্যায় চাপলে তালিকা খুলবে।">
        <div class="dz-lz" data-launch-zones>
            @foreach ($launchZones as $lz)
                <div class="dz-lz-card {{ $launchZone === $lz['key'] ? 'on' : '' }}">
                    <div class="dz-card-head">
                        <div class="font-medium">{{ $lz['name'] }}</div>
                        @if ($lz['cooks'] >= \App\Services\Geo\LaunchZones::COOKS_GOAL) <span class="dz-badge dz-ready">শুরু করা যায়</span>
                        @else <span class="dz-badge dz-some">আরও {{ $bn(\App\Services\Geo\LaunchZones::COOKS_GOAL - $lz['cooks']) }} জন</span> @endif
                    </div>
                    <div class="muted">অনুমোদিত গৃহিণী <b>{{ $bn($lz['cooks']) }} জন</b> · লক্ষ্য {{ $bn(\App\Services\Geo\LaunchZones::COOKS_GOAL) }} জন</div>
                    <div class="muted">দিনে প্লেট <b>{{ $bn($lz['plates']) }}</b> <span title="যাঁদের বাসার লোকেশন আছে শুধু তাঁদের প্লেট; লোকেশন ছাড়া কাউকে গ্রাহক দেওয়া যায় না">(লোকেশন আছে এমন {{ $bn($lz['pinned']) }} জনের)</span></div>
                    <div class="dz-lz-bar"><i style="width: {{ min(100, round($lz['cooks'] / \App\Services\Geo\LaunchZones::COOKS_GOAL * 100)) }}%"></i></div>
                    <div class="dz-lz-nums">
                        @foreach (['all' => [$lz['cooks'], 'গৃহিণী'], 'pin' => [$lz['pinned'], 'লোকেশন আছে'], 'nopin' => [$lz['pinless'], 'লোকেশন নেই'], 'pending' => [$lz['pending'], 'অপেক্ষায়']] as $which => [$n, $label])
                            <button type="button" wire:click="openLaunchZone({{ $lz['key'] }}, '{{ $which }}')" class="{{ $launchZone === $lz['key'] && $launchWhich === $which ? 'sel' : '' }}"><b>{{ $bn($n) }}</b><span>{{ $label }}</span></button>
                        @endforeach
                    </div>
                    <details class="muted" style="margin-top:.5rem"><summary>কোন কোন এলাকা</summary>{{ implode(', ', $lz['areas']) }}</details>
                </div>
            @endforeach
        </div>

        @if ($launchZone !== null)
            @php($open = $launchZones->firstWhere('key', $launchZone))
            <div style="margin-top:1rem" data-launch-list>
                <div class="font-medium" style="margin-bottom:.5rem">{{ $open['name'] ?? '' }} — {{ $whichLabel[$launchWhich] }} ({{ $bn($launchList->count()) }} জন)
                    <button type="button" wire:click="openLaunchZone({{ $launchZone }}, '{{ $launchWhich }}')" class="muted" style="margin-left:.5rem">বন্ধ করুন ✕</button>
                </div>
                @if ($launchList->isEmpty())
                    <p class="text-sm text-gray-500">কেউ নেই।</p>
                @else
                    <table class="dz w-full text-sm">
                        <thead><tr class="text-gray-500"><th>রান্নাঘর</th><th>মোবাইল</th><th>এলাকা</th><th class="r">প্লেট</th><th>লোকেশন</th></tr></thead>
                        <tbody>
                            @foreach ($launchList as $k)
                                <tr>
                                    <td class="font-medium"><a href="{{ \App\Filament\Resources\Kitchens\KitchenResource::getUrl('view', ['record' => $k]) }}" class="text-primary-600">{{ $k->display_name }}</a><div class="muted">{{ $k->user?->name }}</div></td>
                                    <td>{{ $k->user?->phone }}</td>
                                    <td>{{ $k->area?->name }}</td>
                                    <td class="r">{{ $k->daily_plates ? $bn($k->daily_plates) : '—' }}</td>
                                    <td>@if ($k->location)<span class="dz-badge dz-ready">আছে</span>@else<span class="dz-badge dz-none">নেই</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </x-filament::section>

    @if ($showPinLinks)
        <x-filament::section heading="পিন ছাড়া গৃহিণী" description="এঁদের বাসার লোকেশন নেই, তাই কোনো জোনে পড়েননি। লিংক পাঠান — লগইন ছাড়াই খোলে, ৭ দিন চলে।">
            @if ($pinless->isEmpty())
                <p class="text-sm text-gray-500">সবার লোকেশন আছে।</p>
            @else
                <div class="dz-cards">
                    @foreach ($pinless as $row)
                        <div class="dz-card">
                            <div class="dz-card-head">
                                <div>
                                    <div class="font-medium">{{ $row['kitchen']->display_name }}</div>
                                    <div class="muted">{{ $row['kitchen']->user?->name }} · {{ $row['kitchen']->user?->phone }} · {{ $row['kitchen']->area?->name ?? '—' }}</div>
                                    @if ($row['link']->submitted_at)<div class="muted">পিন দিয়েছেন — যাচাই বাকি</div>@endif
                                </div>
                                @if ($row['kitchen']->user?->phone)<a href="https://wa.me/{{ \App\Support\PhoneNumber::international($row['kitchen']->user->phone) }}?text={{ rawurlencode($row['link']->message()) }}" target="_blank" rel="noopener" class="dz-badge dz-ready">WhatsApp</a>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <table class="dz dz-wide w-full text-sm" data-pin-links>
                    <thead><tr class="text-gray-500"><th>রান্নাঘর</th><th>মোবাইল</th><th>এলাকা</th><th>লিংক</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pinless as $row)
                            <tr data-phone="{{ $row['kitchen']->user?->phone }}" data-url="{{ $row['link']->url() }}" data-message="{{ $row['link']->message() }}">
                                <td class="font-medium">{{ $row['kitchen']->display_name }}<div class="muted">{{ $row['kitchen']->user?->name }}</div></td>
                                <td>{{ $row['kitchen']->user?->phone }}</td>
                                <td>{{ $row['kitchen']->area?->name ?? '—' }}</td>
                                <td><a href="{{ $row['link']->url() }}" target="_blank" rel="noopener" class="muted">{{ \Illuminate\Support\Str::after($row['link']->url(), '://') }}</a>@if ($row['link']->submitted_at)<div class="muted">পিন দিয়েছেন — যাচাই বাকি</div>@endif</td>
                                <td>@if ($row['kitchen']->user?->phone)<a href="https://wa.me/{{ \App\Support\PhoneNumber::international($row['kitchen']->user->phone) }}?text={{ rawurlencode($row['link']->message()) }}" target="_blank" rel="noopener" class="dz-badge dz-ready">WhatsApp</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif

    <x-filament::section heading="ম্যাপ" description="সবুজ: ৩+ অনুমোদিত গৃহিণী · হলুদ: ১–২ জন · ধূসর: নেই। বৃত্তে চাপলে নাম আর সংখ্যা। শুধু যাঁদের বাসার পিন আছে তাঁরা ম্যাপে।">
        <div wire:ignore class="dz-map" data-zone-map='@json(['zones' => $mapData, 'radius' => $radius])'></div>
    </x-filament::section>

    <p class="text-sm text-gray-500">দিনে প্লেট (পিনসহ অনুমোদিত গৃহিণী): মোট {{ $bn($totals['plates']) }} · ৮০ ছুঁয়েছে এমন জোন {{ $bn($totals['zones_80']) }} · পিন আছে কিন্তু প্লেট জানাননি {{ $bn($totals['plates_unknown']) }} জন</p>

    @if ($totals['outside'] || $totals['no_area'])
        <p class="text-sm text-gray-500">
            @if ($totals['outside']) পিন সব জোনের বাইরে: {{ $bn($totals['outside']) }} জন। @endif
            @if ($totals['no_area']) পিন বা এলাকা কিছুই নেই: {{ $bn($totals['no_area']) }} জন। @endif
        </p>
    @endif

    @foreach ($areas as $a)
        @php($shown = $onlyBusy ? collect($a['zones'])->filter(fn ($z) => $z['cooks'] + $z['pending'] + $z['customers'] > 0)->values() : collect($a['zones']))
        @php($hidden = count($a['zones']) - $shown->count())
        <x-filament::section :heading="$a['name']" :description="'গৃহিণী '.$bn($a['cooks']).' (অপেক্ষায় '.$bn($a['pending']).') · প্লেট '.$bn($a['plates']).' · গ্রাহক '.$bn($a['customers']).' · এলাকার শাহিদার '.$bn($a['shahidars'])" collapsible :collapsed="$a['cooks'] + $a['pending'] + $a['customers'] === 0">
            <div class="dz-cards">
                @foreach ($shown as $z)
                    <div class="dz-card">
                        <div class="dz-card-head">
                            <div>
                                <div class="font-medium">{{ $z['name'] }}</div>
                                <a class="muted" href="https://www.google.com/maps?q={{ $z['center']->lat }},{{ $z['center']->lng }}" target="_blank" rel="noopener">ম্যাপে দেখুন</a>
                            </div>
                            @if ($z['cooks'] >= 3) <span class="dz-badge dz-ready">কর্পোরেট রিচ করা যায়</span>
                            @elseif ($z['cooks'] > 0) <span class="dz-badge dz-some">আরও গৃহিণী লাগবে</span>
                            @else <span class="dz-badge dz-none">গৃহিণী নেই</span> @endif
                        </div>
                        <div class="dz-nums">
                            <div><b>{{ $bn($z['cooks']) }}</b><span>গৃহিণী</span></div>
                            <div><b>{{ $bn($z['pending']) }}</b><span>অপেক্ষায়</span></div>
                            <div><b @if ($z['plates'] >= \App\Services\Geo\Zones::PLATES_GOAL) style="color:#16a34a" @endif>{{ $bn($z['plates']) }}<small class="muted">/৮০</small></b><span>দিনে প্লেট</span></div>
                            <div><b>{{ $bn($z['customers']) }}</b><span>গ্রাহক</span></div>
                        </div>
                    </div>
                @endforeach
                @if ($a['unpinned']['cooks'] + $a['unpinned']['pending'] + $a['unpinned']['customers'] > 0)
                    <div class="dz-card">
                        <div class="font-medium">পিন ছাড়া</div>
                        <div class="muted">বাসার লোকেশন দেননি — কোন জোনে বলা যাচ্ছে না, প্লেটও গোনা হয় না</div>
                        <div class="dz-nums">
                            <div><b>{{ $bn($a['unpinned']['cooks']) }}</b><span>গৃহিণী</span></div>
                            <div><b>{{ $bn($a['unpinned']['pending']) }}</b><span>অপেক্ষায়</span></div>
                            <div><b>—</b><span>দিনে প্লেট</span></div>
                            <div><b>{{ $bn($a['unpinned']['customers']) }}</b><span>গ্রাহক</span></div>
                        </div>
                    </div>
                @endif
            </div>
            <table class="dz dz-wide w-full text-sm">
                <thead><tr class="text-gray-500"><th>জোন</th><th>মূল ঠিকানা</th><th class="r">গৃহিণী</th><th class="r">অপেক্ষায়</th><th class="r">দিনে প্লেট / ৮০</th><th class="r">গ্রাহক</th><th>অবস্থা</th></tr></thead>
                <tbody>
                    @foreach ($shown as $z)
                        <tr>
                            <td class="font-medium">{{ $z['name'] }}<div class="muted"><a href="https://www.google.com/maps?q={{ $z['center']->lat }},{{ $z['center']->lng }}" target="_blank" rel="noopener">ম্যাপে দেখুন</a></div></td>
                            <td>{{ $a['name'] }}</td>
                            <td class="r">{{ $bn($z['cooks']) }}</td>
                            <td class="r">{{ $bn($z['pending']) }}</td>
                            <td class="r">@if ($z['plates'] >= \App\Services\Geo\Zones::PLATES_GOAL)<span class="dz-badge dz-ready">{{ $bn($z['plates']) }} / ৮০</span>@else{{ $bn($z['plates']) }} / ৮০@endif</td>
                            <td class="r">{{ $bn($z['customers']) }}</td>
                            <td>
                                @if ($z['cooks'] >= 3) <span class="dz-badge dz-ready">কর্পোরেট রিচ করা যায়</span>
                                @elseif ($z['cooks'] > 0) <span class="dz-badge dz-some">আরও গৃহিণী লাগবে</span>
                                @else <span class="dz-badge dz-none">গৃহিণী নেই</span> @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($a['unpinned']['cooks'] + $a['unpinned']['pending'] + $a['unpinned']['customers'] > 0)
                        <tr>
                            <td class="font-medium">পিন ছাড়া<div class="muted">বাসার লোকেশন দেননি — কোন জোনে বলা যাচ্ছে না</div></td>
                            <td>{{ $a['name'] }}</td>
                            <td class="r">{{ $bn($a['unpinned']['cooks']) }}</td>
                            <td class="r">{{ $bn($a['unpinned']['pending']) }}</td>
                            <td class="r muted">পিন ছাড়া গোনা হয় না</td>
                            <td class="r">{{ $bn($a['unpinned']['customers']) }}</td>
                            <td></td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @if ($hidden > 0)
                <p class="muted" style="margin-top:.5rem">আর {{ $bn($hidden) }}টা জোনে এখনো কেউ নেই — "সব এলাকা দেখুন" চাপলে সেগুলোও দেখাবে।</p>
            @endif
        </x-filament::section>
    @endforeach

    <script>
        (function () {
            const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
            function draw() {
                document.querySelectorAll('[data-zone-map]').forEach((el) => {
                    if (el._dz || !window.L) return;
                    el._dz = true;
                    const cfg = JSON.parse(el.dataset.zoneMap);
                    const map = L.map(el, { scrollWheelZoom: false }).setView([23.78, 90.40], 12);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
                    const shapes = [];
                    for (const z of cfg.zones) {
                        const color = z.k >= 3 ? '#16a34a' : (z.k > 0 ? '#d97706' : '#9ca3af');
                        const c = L.circle(z.c, { radius: cfg.radius, color, weight: 1, fillColor: color, fillOpacity: z.k > 0 || z.u > 0 ? 0.3 : 0.05 })
                            .bindPopup(`<b>${z.n}</b><br>গৃহিণী ${bn(z.k)} (অপেক্ষায় ${bn(z.p)})<br>গ্রাহক ${bn(z.u)}`)
                            .addTo(map);
                        if (z.k > 0 || z.u > 0) shapes.push(c);
                    }
                    if (shapes.length) map.fitBounds(L.featureGroup(shapes).getBounds().pad(0.1));
                });
            }
            if (window.L) draw(); else window.addEventListener('load', draw);
            document.addEventListener('livewire:navigated', draw);
        })();
    </script>
</x-filament-panels::page>
