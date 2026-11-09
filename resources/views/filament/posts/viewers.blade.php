@php
    /** F35: who saw this post. Guests are counted, not named. */
    $post = $getRecord();
    $views = $post->views();
    $total = (clone $views)->count();
    $people = (clone $views)->distinct()->count('viewer_key');
    $signedIn = (clone $views)->whereNotNull('user_id')->distinct()->count('user_id');
    $opened = (clone $views)->where('source', 'detail')->count();
    $recent = $post->views()->with(['user:id,name,phone', 'area:id,name'])->latest('id')->limit(30)->get();
    $bn = fn ($n) => \App\Models\Order::banglaDigits($n);
@endphp

<div class="dw-reach">
    <div class="stats">
        @foreach ([['মোট দেখা', $total], ['আলাদা মানুষ', $people], ['লগইন করা', $signedIn], ['পোস্ট খুলেছেন', $opened]] as [$label, $n])
            <div class="stat"><small>{{ $label }}</small><b>{{ $bn($n) }}</b></div>
        @endforeach
    </div>

    @if ($recent->isEmpty())
        <p class="note">এখনো কেউ দেখেননি।</p>
    @else
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>কে</th>
                        <th>কোথা থেকে</th>
                        <th>দূরত্ব</th>
                        <th>কীভাবে</th>
                        <th>কখন</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $v)
                        <tr>
                            <td>{{ $v->user ? ($v->user->name ?: 'নাম নেই').' · '.$v->user->phone : 'অতিথি (লগইন করেননি)' }}</td>
                            <td>{{ $v->area?->name ? \App\Support\Seo::of($v->area->name).' কাছে' : '—' }}</td>
                            <td>{{ $v->distance_m !== null ? \App\Services\Feed\PostReach::km(max(100, $v->distance_m)) : '—' }}</td>
                            <td>{{ $v->source === 'detail' ? 'পোস্ট খুলেছেন' : 'ফিডে দেখেছেন' }}</td>
                            <td>{{ $v->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="legend">একজন একই দিনে বারবার দেখলে একবারই গোনা হয়। অতিথির পরিচয় রাখা হয় না, শুধু কাছের এলাকা।</p>
    @endif
</div>
