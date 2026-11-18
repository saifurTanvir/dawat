{{-- F63: earlier remarks on this person, newest first. --}}
@php($remarks = $remarks ?? collect())
@if ($remarks->isNotEmpty())
    <div style="font-size:.875rem">
        <p style="font-weight:600;margin-bottom:.4rem">আগের রিমার্কস</p>
        <ul style="display:grid;gap:.5rem">
            @foreach ($remarks as $r)
                <li style="border-top:1px solid rgba(156,163,175,.3);padding-top:.5rem">
                    <p>{{ $r->body }}</p>
                    <p style="font-size:.75rem;color:rgb(107 114 128)">
                        {{ $r->author?->name ?? 'অ্যাডমিন' }} · {{ $r->created_at->timezone(config('app.display_timezone'))->format('j M, g:i a') }}
                        @if ($r->follow_up_on)
                            · ফলো-আপ {{ $r->follow_up_on->format('j M') }}{{ $r->done_at ? ' — বন্ধ'.($r->doneBy ? ' ('.$r->doneBy->name.')' : '') : ' — খোলা' }}
                        @endif
                    </p>
                    @if ($r->done_note)
                        <p style="font-size:.75rem;color:rgb(107 114 128)">বন্ধের কারণ: {{ $r->done_note }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
