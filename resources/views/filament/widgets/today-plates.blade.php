@php($bn = fn ($n) => \App\Models\Order::banglaDigits((string) $n))
<x-filament-widgets::widget>
    <x-filament::section heading="আজকের প্লেট" description="কোন গৃহিণীর কোন বেলার পোস্ট এসেছে, নিয়মিত গ্রাহক কয় প্লেট পেলেন আর ফিডে কত গেল।">
        <style>
            .dw-plates { width: 100%; font-size: .875rem; border-collapse: collapse; }
            .dw-plates th, .dw-plates td { padding: 8px 10px 8px 0; text-align: left; vertical-align: top; }
            .dw-plates thead th { color: rgb(107 114 128); font-weight: 500; }
            .dw-plates tbody tr { border-top: 1px solid rgb(229 231 235); }
            .dw-plates .r { text-align: right; }
            .dw-plates .wait { color: rgb(180 83 9); font-weight: 600; }
            .dw-plates .off { color: rgb(107 114 128); }
            .dw-plates .ok { color: rgb(21 128 61); }
            .dw-bar { display: flex; height: 8px; border-radius: 4px; overflow: hidden; background: rgb(229 231 235); margin-top: 4px; min-width: 120px; }
            .dw-bar span { display: block; height: 100%; }
        </style>
        @if (empty($rows))
            <p class="text-sm text-gray-500">আজ কোনো নিয়মিত বুকিং, মেনু বা পোস্ট নেই।</p>
        @else
            <div style="overflow-x:auto">
            <table class="dw-plates">
                <thead><tr><th>রান্নাঘর</th><th>বেলা</th><th>অবস্থা</th><th class="r">নিয়মিত</th><th class="r">দাম জিজ্ঞেস</th><th class="r">ফিডে অর্ডার</th><th class="r">ফিডে বাকি</th><th>প্লেটের ভাগ</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><a href="{{ \App\Filament\Resources\Kitchens\KitchenResource::getUrl('view', ['record' => $r['kitchen']]) }}" style="font-weight:600">{{ $r['kitchen']->display_name }}</a></td>
                            <td>{{ $r['slot']->label() }}</td>
                            <td class="{{ $r['posted'] ? 'ok' : (str_contains($r['status'], 'নেই') ? 'off' : 'wait') }}">
                                {{ $r['status'] }}
                                @if (! $r['posted'] && $r['waiting_people'])
                                    <div class="off" style="font-weight:400">{{ $bn($r['waiting_people']) }} জন নিয়মিত গ্রাহক অপেক্ষায় · {{ $bn($r['waiting_plates']) }} প্লেট</div>
                                @endif
                            </td>
                            <td class="r">{{ $r['posted'] ? $bn($r['regular']).' প্লেট ('.$bn($r['regular_people']).' জন)' : '—' }}</td>
                            <td class="r">{{ $r['asking_people'] ? $bn($r['asking_people']).' জন' : '—' }}</td>
                            <td class="r">{{ $r['posted'] ? $bn($r['feed_orders']) : '—' }}</td>
                            <td class="r">{{ $r['posted'] ? $bn($r['left']) : '—' }}</td>
                            <td>
                                @if ($r['posted'] && $r['total'])
                                    <div class="dw-bar" title="সবুজ নিয়মিত · হলুদ দাম জিজ্ঞেস · নীল ফিডে অর্ডার · ধূসর বাকি">
                                        <span style="width:{{ 100 * $r['regular'] / $r['total'] }}%;background:#1E5B3F"></span>
                                        <span style="width:{{ 100 * $r['asking'] / $r['total'] }}%;background:#E0A526"></span>
                                        <span style="width:{{ 100 * $r['feed_orders'] / $r['total'] }}%;background:#3B6EA8"></span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">রঙ: সবুজ নিয়মিত গ্রাহক · হলুদ দাম জিজ্ঞেস চলছে · নীল ফিড থেকে অর্ডার · ধূসর ফিডে বাকি</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
