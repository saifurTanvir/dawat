@php($bn = fn ($n) => \App\Models\Order::banglaDigits((string) $n))
@php($rows = [
    ['গৃহিণী (অনুমোদিত)', 'cooks', 'cooks_today', 'আজ নতুন'],
    ['পিন আছে', 'pinned', 'pins_today', 'আজ পিন দিয়েছেন'],
    ['পিন নেই', 'pinless', null, null],
    ['দিনে প্লেট (পিন আছে এমন)', 'plates', null, null],
    ['অনুমোদনের অপেক্ষায়', 'pending', 'signups_today', 'আজ যোগ দিয়েছেন'],
    ['গ্রাহক', 'customers', 'customers_today', 'আজ নতুন'],
    ['এলাকা ম্যানেজার আবেদন', 'managers', 'managers_today', 'আজ নতুন'],
])
<x-filament-widgets::widget>
    <x-filament::section heading="বাড্ডা বনাম বাকি ঢাকা" description="এখন সব ফোকাস বাড্ডায় (উত্তর, মধ্য, দক্ষিণ, মেরুল বাড্ডা, শাহজাদপুর)। হিসাব তাঁদের বেছে নেওয়া এলাকা ধরে।">
        <style>
            .dw-focus { width: 100%; font-size: .9rem; border-collapse: collapse; }
            .dw-focus th, .dw-focus td { padding: 8px 10px 8px 0; text-align: left; }
            .dw-focus thead th { color: rgb(107 114 128); font-weight: 500; }
            .dw-focus tbody tr { border-top: 1px solid rgb(229 231 235); }
            .dw-focus .n { font-weight: 700; font-size: 1.05rem; }
            .dw-focus .badda { background: rgba(30, 91, 63, .07); }
            .dw-focus .sub { color: rgb(21 128 61); font-size: .8rem; margin-left: 4px; }
        </style>
        <div style="overflow-x:auto">
            <table class="dw-focus">
                <thead><tr><th></th><th class="badda">বাড্ডা</th><th>বাকি সব এলাকা</th></tr></thead>
                <tbody>
                    @foreach ($rows as [$label, $key, $todayKey, $todayLabel])
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach (['badda', 'rest'] as $side)
                                <td class="{{ $side === 'badda' ? 'badda' : '' }}">
                                    <span class="n">{{ $bn($c[$side][$key]) }}</span>
                                    @if ($todayKey && $c[$side][$todayKey])
                                        <span class="sub">+{{ $bn($c[$side][$todayKey]) }} {{ $todayLabel }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-sm text-gray-500">বাড্ডার লক্ষ্য: {{ $bn(\App\Services\Geo\LaunchZones::COOKS_GOAL) }} জন গৃহিণী · গ্রাহক গৃহিণীর দ্বিগুণ · এলাকা ম্যানেজার ২ জন</p>
    </x-filament::section>
</x-filament-widgets::widget>
