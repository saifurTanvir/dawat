<x-filament-panels::page>
    <div wire:poll.60s class="os">
        <style>
            .os { display: grid; gap: 1rem; }
            .os-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .75rem; }
            .os-card { border: 1px solid rgb(229 231 235); border-radius: .75rem; padding: 1rem; }
            .dark .os-card { border-color: rgba(255,255,255,.1); }
            .os-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-weight: 600; font-size: 1.05rem; }
            .os-badge { border-radius: 999px; padding: 2px 10px; font-size: .8rem; font-weight: 700; white-space: nowrap; }
            .os-ok { background: #dcfce7; color: #166534; }
            .os-problem { background: #fef3c7; color: #92400e; }
            .os-stopped { background: #fee2e2; color: #991b1b; }
            .os-none { background: #f3f4f6; color: #6b7280; }
            .os-nums { display: grid; grid-template-columns: repeat(2, 1fr); gap: .4rem; margin-top: .75rem; text-align: center; }
            .os-nums div { border-radius: .5rem; background: rgba(156,163,175,.12); padding: .45rem .25rem; }
            .os-nums b { display: block; font-size: 1.35rem; }
            .os-nums span, .os-muted { font-size: .78rem; color: rgb(107 114 128); }
            .os-note { margin-top: .6rem; font-size: .85rem; }
            .os-list { width: 100%; font-size: .85rem; }
            .os-list td { padding: 6px 8px 6px 0; vertical-align: top; border-top: 1px solid rgb(229 231 235); }
            .dark .os-list td { border-color: rgba(255,255,255,.1); }
            .os-wa { padding: .5rem 0; border-top: 1px solid rgba(156,163,175,.25); font-size: .9rem; }
            .os-wa summary { cursor: pointer; }
        </style>

        <div class="os-cards">
            @foreach ($channels as $c)
                @php($cls = ['ok' => 'os-ok', 'problem' => 'os-problem', 'stopped' => 'os-stopped', 'none' => 'os-none'][$c['state']])
                @php($txt = ['ok' => '✅ চলছে', 'problem' => '⚠️ সমস্যা', 'stopped' => '⛔ থেমে আছে', 'none' => 'শুরু হয়নি'][$c['state']])
                <div class="os-card">
                    <div class="os-head"><span>{{ $c['label'] }}</span><span class="os-badge {{ $cls }}">{{ $txt }}</span></div>
                    <div class="os-muted" style="margin-top:.25rem">
                        @if ($c['last'])
                            শেষ খবর: {{ $c['last']->created_at->locale('bn')->diffForHumans() }}
                            ({{ $c['last']->created_at->timezone(config('app.display_timezone'))->format('g:i A') }})
                        @else
                            এখনো কোনো খবর আসেনি
                        @endif
                    </div>
                    <div class="os-nums">
                        <div><b>{{ $bn($c['sentToday']) }}</b><span>আজ উত্তর পেয়েছেন</span></div>
                        <div><b>{{ $c['last']?->pending !== null ? $bn($c['last']->pending) : '—' }}</b><span>উত্তর বাকি</span></div>
                    </div>
                    @if ($c['last']?->note)
                        <div class="os-note">{{ $c['last']->note }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="os-cards">
            <div class="os-card"><div class="os-nums" style="margin:0">
                <div><b>{{ $bn($kitchensToday) }}</b><span>আজ নতুন রান্নাঘর</span></div>
                <div><b>{{ $bn($kitchensTotal) }}</b><span>মোট অনুমোদিত</span></div>
                <div><b>{{ $bn($pinless) }}</b><span>পিন ছাড়া</span></div>
                <div><b>{{ $bn($platesKnown) }}</b><span>প্লেট জানা</span></div>
            </div></div>
        </div>

        <x-filament::section heading="WhatsApp চেকের হিসাব" description="প্রতি ঘণ্টায় WhatsApp খুলে দেখা হয়: কখন দেখা হলো, নতুন কী মেসেজ এসেছিল, আর কী করা হলো।">
            @if ($waChecks->isEmpty())
                <div class="os-muted">গত দুই দিনে WhatsApp চেকের কোনো খবর নেই।</div>
            @else
                @foreach ($waChecks as $w)
                    <details class="os-wa" @if ($loop->first) open @endif>
                        <summary>
                            <b>{{ $w->created_at->timezone(config('app.display_timezone'))->locale('bn')->translatedFormat('j M, g:i A') }}</b>
                            — {{ $w->found !== null ? 'নতুন মেসেজ '.$bn($w->found).'টা' : '' }}{{ $w->sent ? ' · উত্তর গেছে '.$bn($w->sent).'জনকে' : '' }}{{ $w->found === 0 ? ' (কিছু আসেনি)' : '' }}{{ $w->problem ? ' · ⚠️ সমস্যা' : '' }}
                            @if ($w->note)<span class="os-muted"> — {{ $w->note }}</span>@endif
                        </summary>
                        @if ($w->details)
                            <table class="os-list" style="margin-top:.4rem">
                                <tr class="os-muted"><td>কে</td><td>কী লিখেছিলেন</td><td>কী করা হলো</td></tr>
                                @foreach ($w->details as $d)
                                    <tr><td style="white-space:nowrap">{{ $d['who'] ?? '' }}</td><td>{{ $d['said'] ?? '' }}</td><td>{{ $d['did'] ?? '' }}</td></tr>
                                @endforeach
                            </table>
                        @endif
                    </details>
                @endforeach
            @endif
        </x-filament::section>

        <x-filament::section heading="সাম্প্রতিক খবর">
            @if ($recent->isEmpty())
                <div class="os-muted">এখনো কিছু নেই।</div>
            @else
                <table class="os-list">
                    @foreach ($recent as $r)
                        <tr>
                            <td class="os-muted" style="white-space:nowrap">{{ $r->created_at->timezone(config('app.display_timezone'))->format('g:i A') }}</td>
                            <td style="white-space:nowrap">{{ \App\Models\OpsHeartbeat::CHANNELS[$r->channel] ?? $r->channel }}</td>
                            <td>{{ $r->problem ? '⚠️ ' : '' }}{{ $r->found !== null ? 'নতুন '.$bn($r->found).' · ' : '' }}{{ $r->sent ? $bn($r->sent).' জনকে উত্তর' : '' }}{{ $r->pending !== null ? ' · বাকি '.$bn($r->pending) : '' }}{{ $r->note ? ' — '.$r->note : '' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
