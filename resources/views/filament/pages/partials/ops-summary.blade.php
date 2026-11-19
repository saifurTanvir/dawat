@php($bn = fn ($n) => \App\Services\Geo\Zones::bn($n))
<a href="{{ \App\Filament\Pages\OpsStatus::getUrl() }}" class="ops-sum" style="display:grid;gap:.4rem;margin:.5rem 0 .25rem;text-decoration:none;color:inherit">
    @foreach ($channels as $c)
        @php($badge = ['ok' => ['✅ চলছে', '#dcfce7', '#166534'], 'problem' => ['⚠️ সমস্যা', '#fef3c7', '#92400e'], 'stopped' => ['⛔ থেমে আছে', '#fee2e2', '#991b1b'], 'none' => ['শুরু হয়নি', '#f3f4f6', '#6b7280']][$c['state']])
        <span style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;font-size:.9rem">
            <b style="min-width:5.5rem">{{ $c['label'] }}</b>
            <span style="border-radius:999px;padding:1px 9px;font-size:.78rem;font-weight:700;background:{{ $badge[1] }};color:{{ $badge[2] }}">{{ $badge[0] }}</span>
            <span style="opacity:.75">আজ {{ $bn($c['sentToday']) }} উত্তর@if ($c['last']?->pending !== null) · বাকি {{ $bn($c['last']->pending) }}@endif @if ($c['last']) · {{ $c['last']->created_at->locale('bn')->diffForHumans() }}@endif</span>
        </span>
    @endforeach
    <span style="font-size:.8rem;color:#22c55e">বিস্তারিত দেখুন →</span>
</a>
