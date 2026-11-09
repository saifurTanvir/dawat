@php
    /** F35: where this post shows. Admin only, so the post's own point is drawn. */
    $post = $getRecord()->loadMissing('reachAreas');
    $reach = app(\App\Services\Feed\PostReach::class);
    $rows = $reach->coverage($post);
    $viewsByArea = $post->views()->selectRaw('area_id, count(*) as n')->groupBy('area_id')->pluck('n', 'area_id');
    $extraIds = $post->reachAreas->pluck('id')->all();
    $config = [
        'post' => $post->location ? [$post->location->lat, $post->location->lng] : null,
        'reach_m' => $post->reach_m,
        'tiers' => [1000, 3000, 8000],
        'areas' => collect($rows)->map(fn ($r) => [
            'name' => $r['area']->name,
            'center' => [$r['area']->center->lat, $r['area']->center->lng],
            'radius' => $r['area']->radius_m,
            'extra' => in_array($r['area']->id, $extraIds, true),
            'views' => (int) ($viewsByArea[$r['area']->id] ?? 0),
        ])->values()->all(),
    ];
    $bn = fn ($n) => \App\Models\Order::banglaDigits($n);
@endphp

<div class="dw-reach">
<style>
.dw-reach table{width:100%;font-size:.875rem;border-collapse:collapse}
.dw-reach th{color:rgb(107 114 128);font-weight:500;text-align:left;padding:8px 16px 8px 0}
.dw-reach td{padding:8px 16px 8px 0;border-top:1px solid rgb(229 231 235)}
.dw-reach .note{font-size:.875rem;color:rgb(75 85 99);margin:0 0 12px}
.dw-reach .legend{font-size:.75rem;color:rgb(107 114 128);margin:8px 0 16px}
.dw-reach .map{height:22rem;width:100%;border-radius:12px;overflow:hidden;border:1px solid rgb(229 231 235);position:relative;z-index:0}
.dw-reach .always{color:#1e5b3f;font-weight:600}
.dw-reach .never{color:rgb(156 163 175)}
.dw-reach .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:16px}
.dw-reach .stat{background:rgb(249 250 251);border-radius:12px;padding:12px}
.dw-reach .stat small{display:block;font-size:.75rem;color:rgb(107 114 128)}
.dw-reach .stat b{font-size:1.5rem;font-weight:600}
.dw-reach .scroll{overflow-x:auto}
.dark .dw-reach td{border-color:rgba(255,255,255,.08)} .dark .dw-reach .stat{background:rgba(255,255,255,.05)} .dark .dw-reach .note{color:rgb(209 213 219)}
</style>
    <p class="note">
        পরিধি: <strong>{{ \App\Services\Feed\PostReach::summary($post->reach_m, count($extraIds)) }}</strong>.
        সাধারণভাবে ক্রেতা নিজের লোকেশন থেকে ৫ মিনিট হাঁটা, ১ কিমি, ৩ কিমি বা "পুরো এলাকা" (৮ কিমি) বাছেন; সেই দূরত্বে এই পোস্ট থাকলে দেখেন।
        বাড়াতে ওপরের "পরিধি বদলান" চাপুন।
    </p>

    <div data-reach-map='@json($config)' class="map"></div>
    <p class="legend">
        সবুজ বিন্দু = পোস্ট · ধূসর দাগ = ১, ৩ ও ৮ কিমি · সবুজ বৃত্ত = বাড়ানো পরিধি · কমলা = যোগ করা এলাকা · সংখ্যা = ওই এলাকা থেকে কতজন দেখেছেন
    </p>

    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>এলাকা</th>
                    <th>দূরত্ব</th>
                    <th>কারা দেখবে</th>
                    <th>দেখেছে</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $r['area']->name }}</td>
                        <td>{{ \App\Services\Feed\PostReach::km(max(100, (int) round($r['distance_m'] / 100) * 100)) }}</td>
                        <td class="{{ $r['always'] ? 'always' : ($r['how'] === 'দেখবে না' ? 'never' : '') }}">{{ $r['how'] }}</td>
                        <td>{{ $bn((int) ($viewsByArea[$r['area']->id] ?? 0)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin-reach-map.js')
