{{-- F80: one Messenger chat, oldest first. Inline styles: the admin theme does not compile custom classes. --}}
<div x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" style="max-height: 55vh; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding: 4px;">
    @php($facts = $thread->facts ?? [])
    @if ($facts)
        <div style="font-size: 12px; opacity: .75; margin-bottom: 4px;">
            বট যা জেনেছে:
            @foreach (['name' => 'নাম', 'phone' => 'নম্বর', 'area_text' => 'এলাকা', 'plates' => 'প্লেট'] as $k => $label)
                @if (filled($facts[$k] ?? null)) {{ $label }}: <strong>{{ $facts[$k] }}</strong> · @endif
            @endforeach
            {{ ($facts['cook'] ?? false) ? 'রান্না করতে চান' : '' }}
        </div>
    @endif
    @forelse ($messages as $m)
        <div style="align-self: {{ $m->direction === 'in' ? 'flex-start' : 'flex-end' }}; max-width: 80%;">
            <div style="padding: 6px 10px; border-radius: 12px; white-space: pre-wrap; word-break: break-word; font-size: 14px;
                background: {{ $m->direction === 'in' ? 'rgba(127,127,127,.15)' : ($m->by === 'bot' ? 'rgba(22,163,74,.15)' : 'rgba(37,99,235,.15)') }};">{{ $m->text ?: '' }}@foreach ($m->attachments ?? [] as $a)
                    [{{ $a['type'] === 'location' ? 'লোকেশন' : ($a['type'] === 'image' ? 'ছবি' : ($a['type'] ?? 'ফাইল')) }}]@endforeach</div>
            <div style="font-size: 11px; opacity: .6; text-align: {{ $m->direction === 'in' ? 'left' : 'right' }};">
                {{ \App\Models\MessengerMessage::BY[$m->by] ?? $m->by }} · {{ $m->created_at?->timezone('Asia/Dhaka')->format('j M, g:i a') }}
            </div>
        </div>
    @empty
        <p style="opacity: .7;">কোনো মেসেজ নেই।</p>
    @endforelse
</div>
