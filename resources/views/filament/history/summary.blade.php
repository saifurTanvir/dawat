{{-- F49: summary numbers; $stats = list of [label, value, hint] --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
    @foreach ($stats as $s)
        <div style="border:1px solid rgb(229 231 235);border-radius:12px;padding:10px 12px">
            <div style="font-size:.75rem;color:rgb(107 114 128)">{{ $s['label'] }}</div>
            <div style="font-size:1.25rem;font-weight:700">{{ $s['value'] }}</div>
            @if (! empty($s['hint']))<div style="font-size:.75rem;color:rgb(107 114 128)">{{ $s['hint'] }}</div>@endif
        </div>
    @endforeach
</div>
