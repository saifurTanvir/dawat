@php
    $bn = fn ($n) => \App\Models\Order::banglaDigits($n);
    $tk = fn (int $paisa) => '৳'.$bn(number_format(intdiv($paisa, 100)));
    $date = fn ($d) => $bn($d->format('j')).' '.['জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'][$d->month - 1];
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1E5B3F">
    @include('partials.meta')
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    @vite(['resources/css/app.css'])
    <style>@media print { .no-print { display: none !important } body { background: #fff } }</style>
</head>
<body>
<main class="mx-auto max-w-lg px-4 pt-5 pb-10">
    <header class="flex items-center justify-between">
        <span class="flex items-center gap-2">
            <img src="/brand/mark.png" alt="" width="63" height="32" class="h-8 w-auto">
            <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="h-8 w-auto">
        </span>
        <button type="button" class="no-print btn-quiet -mr-4 min-h-10" onclick="window.print()">প্রিন্ট</button>
    </header>

    <section class="mt-5 rounded-[var(--radius-plate)] bg-plate p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="font-serif text-2xl font-bold">বিল {{ $bill->number }}</h1>
                <p class="text-muted">{{ $bill->customer->user->name ?: 'গ্রাহক' }} · {{ $date($bill->period_from) }} – {{ $date($bill->period_to) }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $bill->status === 'paid' ? 'bg-leaf-soft text-leaf' : 'bg-turmeric-soft' }}">{{ $bill->statusLabel() }}</span>
        </div>

        <table class="mt-4 w-full text-sm">
            <thead>
                <tr class="text-left text-muted">
                    <th class="py-1 font-normal">তারিখ</th>
                    <th class="py-1 font-normal">খাবার</th>
                    <th class="py-1 text-right font-normal">টাকা</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bill->orders as $o)
                    <tr class="border-t border-rule align-top">
                        <td class="py-2 pr-2 whitespace-nowrap">{{ $date($o->service_date) }}<br><span class="text-muted">{{ $o->meal_slot->label() }}</span></td>
                        <td class="py-2 pr-2">{{ $o->title ?: $o->kitchen->display_name }} · {{ $bn($o->plates) }} প্লেট
                            @if ($o->status === \App\Enums\OrderStatus::Failed)<br><span class="text-chili">বাসায় কেউ না থাকায় ফেরত</span>@endif
                        </td>
                        <td class="py-2 text-right whitespace-nowrap">{{ $tk($o->totalPaisa()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mt-4 space-y-1 border-t border-rule pt-3">
            <div class="flex justify-between"><dt class="text-muted">খাবার ({{ $bn($bill->orders_count) }}টা বক্স)</dt><dd>{{ $tk($bill->food_paisa) }}</dd></div>
            @if ($bill->delivery_paisa)
                <div class="flex justify-between"><dt class="text-muted">ডেলিভারি</dt><dd>{{ $tk($bill->delivery_paisa) }}</dd></div>
            @endif
            <div class="flex justify-between text-lg font-semibold"><dt>মোট</dt><dd>{{ $tk($bill->total_paisa) }}</dd></div>
            @if ($bill->paid_paisa)
                <div class="flex justify-between"><dt class="text-muted">পরিশোধ</dt><dd>{{ $tk($bill->paid_paisa) }}</dd></div>
            @endif
            @if ($bill->duePaisa())
                <div class="flex justify-between text-lg font-semibold text-chili"><dt>বাকি</dt><dd>{{ $tk($bill->duePaisa()) }}</dd></div>
            @endif
        </dl>

        @if ($bill->payments->isNotEmpty())
            <p class="mt-4 text-sm font-semibold">যা পেয়েছি</p>
            <ul class="mt-1 text-sm text-muted">
                @foreach ($bill->payments as $p)
                    <li>{{ $date($p->created_at) }} · {{ $tk($p->amount_paisa) }} · {{ \App\Models\Bill::METHOD_LABELS[$p->method] ?? $p->method }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($bill->duePaisa())
        <section class="mt-4 rounded-[var(--radius-plate)] bg-leaf-soft p-4 text-sm">
            <p class="font-semibold">কীভাবে দেবেন</p>
            <p class="mt-1">বিকাশ বা নগদে দাওয়াত টিমকে দিন। কীভাবে দেবেন জানতে ফোন করুন:
                <a href="tel:{{ \App\Support\Seo::phoneIntl() }}" class="font-semibold">{{ \App\Support\Seo::phoneBangla() }}</a>।
                @if ($bill->due_on) {{ $date($bill->due_on) }}-এর মধ্যে দিলে ভালো হয়। @endif
            </p>
        </section>
    @endif

    <p class="mt-6 text-center text-xs text-muted">দাওয়াত — পাড়ার ঘরের খাবার · dawat.cloud</p>
</main>
</body>
</html>
