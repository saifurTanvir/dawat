@php
    $rows = \App\Models\KitchenAction::where('kitchen_id', $getRecord()->id)->with('user')->latest('id')->limit(30)->get();
@endphp
<div style="font-size:.875rem">
    @if ($rows->isEmpty())
        <p style="color:rgb(107 114 128)">গৃহিণীর হয়ে এখনো কিছু করা হয়নি।</p>
    @else
        <ul style="display:flex;flex-direction:column;gap:6px">
            @foreach ($rows as $r)
                <li>
                    <span style="color:rgb(107 114 128)">{{ $r->created_at->timezone(config('app.display_timezone'))->format('j M, g:i a') }}</span>
                    · <strong>{{ $r->whoLabel() }}</strong> · {{ $r->details }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
