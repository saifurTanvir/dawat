@php($tk = fn (int $paisa) => '৳'.number_format(intdiv($paisa, 100)))
<style>.dawat-money th, .dawat-money td { padding: 8px 12px 8px 0; text-align: left; vertical-align: middle; } .dawat-money .r { text-align: right; } .dawat-money tbody tr { border-top: 1px solid rgb(229 231 235); } .dawat-money .muted { color: rgb(107 114 128); }</style>
<x-filament-panels::page>
    <x-filament::section heading="শাহিদারের কাছে নগদ" description="ডেলিভারিতে তোলা টাকা, এখনো দাওয়াতকে জমা দেওয়া হয়নি।">
        @if ($shahidars->isEmpty())
            <p class="text-sm text-gray-500">কারো কাছে জমা টাকা নেই।</p>
        @else
            <table class="dawat-money w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">শাহিদার</th><th>বক্স</th><th class="r">টাকা</th><th></th></tr></thead>
                <tbody>
                    @foreach ($shahidars as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 font-medium">{{ $row['shahidar']->name }} <span class="muted">{{ $row['shahidar']->phone }}</span></td>
                            <td>{{ $row['orders'] }}</td>
                            <td class="r" style="font-weight:600">{{ $tk($row['cash']) }}</td>
                            <td class="r">{{ ($this->receiveCashAction)(['shahidar' => $row['shahidar']->id]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="গৃহিণীদের পাওনা" :description="'পৌঁছানো বা রান্না-হয়ে-ফেরত আসা বক্সের খাবারের দাম'.($commission > 0 ? ', দাওয়াতের '.$commission.'% বাদে।' : '।')">
        @if ($cooks->isEmpty())
            <p class="text-sm text-gray-500">কারো পাওনা বাকি নেই।</p>
        @else
            <table class="dawat-money w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">রান্নাঘর</th><th>সময়</th><th>বক্স</th><th class="r">খাবার</th><th class="r">কমিশন</th><th class="r">দিতে হবে</th><th></th></tr></thead>
                <tbody>
                    @foreach ($cooks as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 font-medium">{{ $row['kitchen']->display_name }} <span class="muted">{{ $row['kitchen']->user->phone }}</span></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row['from'])->format('j M') }} – {{ \Illuminate\Support\Carbon::parse($row['to'])->format('j M') }}</td>
                            <td>{{ $row['orders'] }}</td>
                            <td class="r">{{ $tk($row['gross']) }}</td>
                            <td class="r">{{ $tk($row['commission']) }}</td>
                            <td class="r" style="font-weight:600">{{ $tk($row['payable']) }}</td>
                            <td class="r">{{ ($this->payCookAction)(['kitchen' => $row['kitchen']->id]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="গ্রাহকের বাকি" description="খাবার পেয়েছেন কিন্তু টাকা তোলা হয়নি, বা বাসায় না থাকায় ফেরত গেছে।" collapsible>
        @if ($owing->isEmpty())
            <p class="text-sm text-gray-500">কারো বাকি নেই।</p>
        @else
            <table class="dawat-money w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">বক্স</th><th>গ্রাহক</th><th>শাহিদার</th><th>অবস্থা</th><th class="r">টাকা</th></tr></thead>
                <tbody>
                    @foreach ($owing as $o)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2">{{ $o->code() }}</td>
                            <td>{{ $o->customer->user->name }} <span class="muted">{{ $o->customer->user->phone }}</span></td>
                            <td>{{ $o->shahidar->name }}</td>
                            <td>{{ $o->status->label() }}</td>
                            <td class="r">{{ $tk($o->totalPaisa()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="সাম্প্রতিক হিসাব" collapsible collapsed>
        @if ($recent->isEmpty())
            <p class="text-sm text-gray-500">এখনো কোনো হিসাব হয়নি।</p>
        @else
            <ul class="space-y-1 text-sm">
                @foreach ($recent as $s)
                    <li>
                        {{ $s->created_at->timezone(config('app.display_timezone'))->format('j M, g:i a') }} ·
                        {{ $s->kind === 'cook' ? 'পরিশোধ: '.$s->kitchen?->display_name : 'জমা নেওয়া: '.$s->shahidar?->name }} ·
                        {{ $s->orders_count }} বক্স · <strong>{{ $tk($s->amount_paisa) }}</strong>
                        @if ($s->note) <span class="muted">({{ $s->note }})</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
