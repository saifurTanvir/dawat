@php
    $planColor = ['new' => 'success', 'add_role' => 'info', 'exists' => 'gray', 'error' => 'danger'];
    $resultColor = ['added' => 'success', 'role_added' => 'info', 'skipped' => 'gray', 'failed' => 'danger'];
@endphp
<x-filament-panels::page>
    <style>
        .dawat-imp { width:100%; border-collapse:collapse; font-size:.875rem; }
        .dawat-imp th { text-align:start; color:rgb(107 114 128); font-weight:500; padding:.5rem .75rem .5rem 0; white-space:nowrap; }
        .dawat-imp td { padding:.5rem .75rem .5rem 0; border-top:1px solid rgba(156,163,175,.25); vertical-align:top; }
        .dawat-imp td.nowrap { white-space:nowrap; }
        .dawat-badges { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem; }
    </style>
    @if (! $import)
        <x-filament::section heading="কীভাবে" description="মার্কেটিংয়ের তালিকা থেকে গ্রাহক, গৃহিণী আর শাহিদার একসাথে যোগ করুন।">
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                <li>"টেমপ্লেট নামান" — এক্সেল ফাইলে আসল এলাকার নামসহ কলামগুলো আছে।</li>
                <li>"তালিকা" শিটে প্রতি লাইনে একজন: মোবাইল, নাম, ভূমিকা (গ্রাহক / গৃহিণী / শাহিদার), এলাকা; গ্রাহকের ঠিকানা।</li>
                <li>"ফাইল আপলোড করুন" — আগে দেখাবে প্রতিটা লাইনে কী হবে, তারপর আপনি "যোগ করুন" চাপবেন।</li>
                <li>কাউকে SMS যাবে না। গৃহিণীরা অনুমোদনের অপেক্ষায় থাকবেন।</li>
            </ol>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">{{ $import->file_name }}</x-slot>
            <x-slot name="description">
                {{ $import->uploader?->name }} · {{ $import->created_at->timezone(config('app.display_timezone'))->format('j M, g:i a') }} · মোট {{ $import->total }} লাইন
                @if ($import->status === 'done') · যোগ হয়েছে {{ $import->completed_at?->timezone(config('app.display_timezone'))->format('j M, g:i a') }} @endif
            </x-slot>
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">{{ $this->runAction }} {{ $this->resultsAction }} {{ $this->cancelAction }}</div>
            </x-slot>

            <div class="dawat-badges">
                @if ($import->status === 'done')
                    <x-filament::badge color="success">নতুন যোগ {{ $import->added }}</x-filament::badge>
                    <x-filament::badge color="info">ভূমিকা যোগ {{ $import->role_added }}</x-filament::badge>
                    <x-filament::badge color="gray">বাদ {{ $import->skipped }}</x-filament::badge>
                    <x-filament::badge color="danger">হয়নি {{ $import->failed }}</x-filament::badge>
                @else
                    @foreach ($plans as $key => $label)
                        <x-filament::badge :color="$planColor[$key]">{{ $label }}: {{ $counts[$key] ?? 0 }}</x-filament::badge>
                    @endforeach
                @endif
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="dawat-imp">
                    <thead><tr class="text-left text-gray-500"><th class="py-2 pe-3">লাইন</th><th class="pe-3">নাম</th><th class="pe-3">মোবাইল</th><th class="pe-3">ভূমিকা</th><th class="pe-3">অবস্থা</th><th>কারণ / নোট</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-gray-200 align-top dark:border-white/10">
                                <td class="py-2 pe-3 text-gray-500">{{ $row->line }}</td>
                                <td class="nowrap" style="font-weight:600">{{ $row->name ?: '—' }}</td>
                                <td class="nowrap">{{ $row->phone ?: '—' }}</td>
                                <td class="nowrap">{{ $roles[$row->role] ?? '—' }}</td>
                                <td class="nowrap">
                                    @if ($row->result)
                                        <x-filament::badge :color="$resultColor[$row->result] ?? 'gray'">{{ $results[$row->result] ?? $row->result }}</x-filament::badge>
                                    @else
                                        <x-filament::badge :color="$planColor[$row->plan] ?? 'gray'">{{ $plans[$row->plan] ?? $row->plan }}</x-filament::badge>
                                    @endif
                                </td>
                                <td class="text-gray-600 dark:text-gray-400">{{ $row->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($import->total > $rows->count())
                    <p class="mt-2 text-sm text-gray-500">প্রথম {{ $rows->count() }} লাইন দেখানো হলো (ভুলগুলো আগে)। সব লাইন "ফলাফল নামান" ফাইলে।</p>
                @endif
            </div>
        </x-filament::section>
    @endif

    @if ($history->isNotEmpty())
        <x-filament::section heading="আগের তালিকা" collapsible :collapsed="(bool) $import">
            <table class="dawat-imp">
                <thead><tr class="text-left text-gray-500"><th class="py-2">ফাইল</th><th>কে</th><th>কবে</th><th>লাইন</th><th>যোগ</th><th>হয়নি</th><th></th></tr></thead>
                <tbody>
                    @foreach ($history as $h)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2 font-medium">{{ $h->file_name }}</td>
                            <td>{{ $h->uploader?->name }}</td>
                            <td>{{ $h->created_at->timezone(config('app.display_timezone'))->format('j M, g:i a') }}</td>
                            <td>{{ $h->total }}</td>
                            <td>{{ $h->status === 'done' ? $h->added + $h->role_added : 'দেখা বাকি' }}</td>
                            <td>{{ $h->status === 'done' ? $h->failed : '—' }}</td>
                            <td><x-filament::link tag="button" wire:click="open({{ $h->id }})">খুলুন</x-filament::link></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
