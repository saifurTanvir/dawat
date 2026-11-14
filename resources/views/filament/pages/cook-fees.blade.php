@php($tk = fn (int $p) => '৳'.\App\Models\Order::banglaDigits(rtrim(rtrim(number_format($p / 100, 2), '0'), '.')))
<x-filament-panels::page>
    <x-filament::section heading="সবার জন্য" description="গৃহিণী দাম লিখলেই অ্যাপে দেখেন কত কাটা যাবে আর তিনি কত পাবেন। ক্রেতা নিজে এসে নিলে কিছু কাটা হয় না।">
        <dl class="grid gap-4 sm:grid-cols-3 text-sm">
            @foreach ($global as $part => $rule)
                <div><dt class="text-gray-500">{{ $labels[$part] }}</dt><dd class="text-lg font-semibold">{{ \App\Services\Money\CookFees::ruleText($rule) }}</dd></div>
            @endforeach
            <div>
                <dt class="text-gray-500">উদাহরণ: ৳১০০ দামের এক প্লেট</dt>
                <dd class="text-lg font-semibold">গৃহিণী পাবেন {{ $tk($example['cook']) }}</dd>
                <dd class="text-gray-500">ফি {{ $tk($example['platform']) }} · ডেলিভারি {{ $tk($example['delivery']) }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="এলাকার নিজের নিয়ম" description="এলাকা পাতায় এলাকা এডিট করে বসান।">
        @if ($areas->isEmpty())
            <p class="text-sm text-gray-500">কোনো এলাকার আলাদা নিয়ম নেই।</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">এলাকা</th><th>{{ $labels['platform'] }}</th><th>{{ $labels['delivery'] }}</th></tr></thead>
                <tbody>
                    @foreach ($areas as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-2 font-medium">{{ $row['name'] }}</td><td>{{ $row['rules']['platform'] }}</td><td>{{ $row['rules']['delivery'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="গৃহিণীর নিজের নিয়ম" description="রান্নাঘর এডিট করে বসান। এটাই সবার আগে লাগে।">
        @if ($kitchens->isEmpty())
            <p class="text-sm text-gray-500">কোনো গৃহিণীর আলাদা নিয়ম নেই।</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">রান্নাঘর</th><th>এলাকা</th><th>{{ $labels['platform'] }}</th><th>{{ $labels['delivery'] }}</th><th>৳১০০-তে পাবেন</th></tr></thead>
                <tbody>
                    @foreach ($kitchens as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 font-medium"><a href="{{ $row['url'] }}" class="text-primary-600 hover:underline">{{ $row['name'] }}</a></td>
                            <td>{{ $row['area'] ?? '—' }}</td><td>{{ $row['rules']['platform'] }}</td><td>{{ $row['rules']['delivery'] }}</td>
                            <td class="font-semibold">{{ $tk($row['example']['cook']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
