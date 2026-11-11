@php
    $split = app(\App\Services\Posts\PlateSplit::class);
    $s = $split->forPost($getRecord());
    $people = $split->people($getRecord());
@endphp
<div>
    <style>
        .dw-pl th, .dw-pl td { padding: 6px 12px 6px 0; text-align: left; font-size: .875rem; }
        .dw-pl tbody tr { border-top: 1px solid rgb(229 231 235); }
        .dw-pl thead th { color: rgb(107 114 128); font-weight: 500; }
    </style>
    <p style="font-weight:600">{{ \App\Services\Posts\PlateSplit::line($s) }} <span style="color:rgb(107 114 128);font-weight:400">(মোট {{ \App\Models\Order::banglaDigits($s['total']) }} প্লেট)</span></p>
    @if ($people->isEmpty())
        <p style="color:rgb(107 114 128);font-size:.875rem;margin-top:6px">এই পোস্ট থেকে এখনো কোনো বক্স হয়নি।</p>
    @else
        <table class="dw-pl" style="width:100%;margin-top:8px">
            <thead><tr><th>বক্স</th><th>কে</th><th>কীভাবে</th><th>প্লেট</th><th>অবস্থা</th><th>শাহিদার</th></tr></thead>
            <tbody>
                @foreach ($people as $p)
                    <tr><td>{{ $p['code'] }}</td><td>{{ $p['name'] }}</td><td>{{ $p['kind'] }}</td><td>{{ $p['plates'] }}</td><td>{{ $p['status'] }}</td><td>{{ $p['shahidar'] ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
