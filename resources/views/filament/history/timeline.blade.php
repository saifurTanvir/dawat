@php($events = app(\App\Services\Admin\History::class)->timeline($getRecord()))
<ol style="border-left:2px solid rgb(209 213 219);margin-left:6px;padding-left:16px;display:flex;flex-direction:column;gap:12px">
    @foreach ($events as $e)
        <li style="position:relative;font-size:.875rem">
            <span style="position:absolute;left:-23px;top:4px;width:12px;height:12px;border-radius:9999px;background:#1E5B3F"></span>
            <div style="font-weight:600">
                @if ($e['url'])<a href="{{ $e['url'] }}" style="text-decoration:underline">{{ $e['title'] }}</a>@else{{ $e['title'] }}@endif
                <span style="color:rgb(107 114 128);font-weight:400">· {{ $e['at']?->timezone(config('app.display_timezone'))->format('j M, g:i a') }}</span>
            </div>
            @if ($e['detail'])<div style="color:rgb(75 85 99)">{{ $e['detail'] }}</div>@endif
        </li>
    @endforeach
</ol>
