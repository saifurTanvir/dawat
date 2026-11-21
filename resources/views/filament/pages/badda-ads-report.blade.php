@php($bn = fn ($n) => \App\Models\Order::banglaDigits((string) $n))
@php($rows = [
    ['মোট যোগাযোগ', 'total', 'answered'],
    ['Messenger-এ মেসেজ', 'messenger', 'messenger_answered'],
    ['WhatsApp-এ মেসেজ', 'whatsapp', 'whatsapp_answered'],
    ['কমেন্ট', 'comments', 'comments_answered'],
])
<x-filament-panels::page>
    <style>
        .ba-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .ba-t { width: 100%; font-size: .9rem; border-collapse: collapse; }
        .ba-t td { padding: 6px 8px 6px 0; border-top: 1px solid rgb(229 231 235); vertical-align: top; }
        .ba-n { font-weight: 700; font-size: 1.05rem; }
        .ba-ok { color: rgb(21 128 61); font-size: .8rem; }
        .ba-wait { color: rgb(180 83 9); font-size: .8rem; }
        .ba-muted { color: rgb(107 114 128); font-size: .8rem; }
    </style>

    <div class="ba-grid">
        @foreach ($ads as $key => $a)
            <x-filament::section :heading="$a['label'].' অ্যাড'">
                <table class="ba-t">
                    @foreach ($rows as [$label, $n, $ans])
                        <tr>
                            <td>{{ $label }}</td>
                            <td><span class="ba-n">{{ $bn($a[$n]) }}</span></td>
                            <td>
                                @if ($a[$n])
                                    <span class="{{ $a[$ans] < $a[$n] ? 'ba-wait' : 'ba-ok' }}">উত্তর দিয়েছি {{ $bn($a[$ans]) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($a['messenger_waiting'])
                        <tr><td colspan="3" class="ba-wait">Messenger-এ "মানুষ লাগবে": {{ $bn($a['messenger_waiting']) }}</td></tr>
                    @endif
                </table>

                <p class="mt-4 mb-1 text-sm font-semibold">ফলাফল</p>
                <table class="ba-t">
                    @foreach ($a['results'] as [$label, $n])
                        <tr><td>{{ $label }}</td><td class="ba-n" style="text-align:right">{{ $bn($n) }}</td></tr>
                    @endforeach
                </table>

                @if ($a['recent'])
                    <p class="mt-4 mb-1 text-sm font-semibold">সবশেষে যাঁরা এসেছেন</p>
                    <table class="ba-t">
                        @foreach ($a['recent'] as $r)
                            <tr>
                                <td>
                                    {{ $r['name'] }}
                                    @if ($r['phone']) <span class="ba-muted">· {{ $r['phone'] }}</span> @endif
                                    <div class="ba-muted">{{ $r['channel'] }} · {{ $r['at']?->timezone('Asia/Dhaka')->diffForHumans() }}</div>
                                </td>
                                <td class="{{ $r['answered'] ? 'ba-ok' : 'ba-wait' }}" style="text-align:right">{{ $r['answered'] ? 'উত্তর গেছে' : 'উত্তর বাকি' }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </x-filament::section>
        @endforeach
    </div>

    <p class="ba-muted">
        Messenger: অ্যাড থেকে আসা চ্যাট (অ্যাডের আইডি বা অ্যাডের শুভেচ্ছা মেসেজ দেখে); গৃহিণী অ্যাডের শুভেচ্ছা বাকি ঢাকার অ্যাডেও আছে, তাই আইডি না থাকলে শুধু বাড্ডার চ্যাট ধরা হয়।
        WhatsApp: প্রতি ঘণ্টার WhatsApp চেকে যোগ হয়।
        কমেন্ট: প্রতি ১৫ মিনিটে অ্যাডের পোস্ট থেকে আনা হয়@if ($commentsSynced) (শেষবার {{ \Illuminate\Support\Carbon::parse($commentsSynced)->timezone('Asia/Dhaka')->diffForHumans() }})@endif.
        @if ($commentsError) <span class="ba-wait">কমেন্ট আনা যায়নি: {{ $commentsError }}</span> @endif
        ফলাফলের সংখ্যা অ্যাড চালুর পর থেকে বাড্ডায় যোগ হওয়া সবাই (অন্য পথে এলেও)।
    </p>
</x-filament-panels::page>
