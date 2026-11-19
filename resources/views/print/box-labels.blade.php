<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>বক্সের স্টিকার — {{ count($labels) }}টা</title>
    @vite(['resources/css/app.css'])
    <style>
        /* F79: one 75 × 100 mm thermal label = three stickers, 75 × 33.3 mm each. Pure black. */
        @page { size: 75mm 100mm; margin: 0; }
        html, body { margin: 0; }
        .label { width: 75mm; height: 100mm; display: grid; grid-template-rows: repeat(3, 1fr); box-sizing: border-box; overflow: hidden; break-after: page; page-break-after: always; background: #fff; position: relative; color: #000; }
        .label:last-child { break-after: auto; page-break-after: auto; }
        .label::before, .label::after { content: ''; position: absolute; left: 2mm; right: 2mm; border-top: 0.25mm dashed #000; }
        .label::before { top: 33.33mm; }
        .label::after { top: 66.67mm; }
        /* Box QR | who and where | call QR in its own frame. The name leads; the two QRs sit at
           opposite ends so they are never confused. */
        .sticker { height: 33.33mm; box-sizing: border-box; padding: 1.8mm 2.2mm; display: grid; grid-template-columns: 20mm 1fr 13.5mm; gap: 2mm; align-items: center; overflow: hidden; font-family: 'Noto Sans Bengali', sans-serif; }
        .boxqr svg { width: 20mm; height: 20mm; display: block; shape-rendering: crispEdges; }
        .code { font: 700 6.5pt/1.2 ui-monospace, 'DejaVu Sans Mono', monospace; text-align: center; margin-top: .4mm; }
        .brand { font: 700 6.5pt/1.2 'Noto Serif Bengali', serif; text-align: center; }
        .mid { min-width: 0; overflow: hidden; align-self: stretch; display: flex; flex-direction: column; justify-content: center; }
        .name { font: 700 12pt/1.15 'Noto Serif Bengali', serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tags { display: flex; align-items: center; gap: 1.2mm; margin-top: .7mm; min-width: 0; }
        .tag { flex: none; font: 700 8pt/1 'Noto Serif Bengali', serif; padding: .7mm 1.4mm; border-radius: 1mm; white-space: nowrap; }
        .tag-office { background: #000; color: #fff; }
        .tag-meal { border: .3mm solid #000; }
        .phone { font: 700 8.5pt/1.2 ui-monospace, 'DejaVu Sans Mono', monospace; white-space: nowrap; margin-top: .6mm; }
        .addr { font-size: 7pt; line-height: 1.25; margin-top: .4mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .addr b { font-weight: 700; }
        .call { border: .3mm solid #000; border-radius: 1.4mm; padding: .8mm .8mm .5mm; text-align: center; }
        .call svg { width: 11.5mm; height: 11.5mm; display: block; margin: 0 auto; shape-rendering: crispEdges; }
        .call small { display: block; font: 700 6pt/1.15 'Noto Sans Bengali', sans-serif; margin-top: .3mm; }
        @media screen {
            body { background: #e8ece9; }
            .pages { display: flex; flex-wrap: wrap; gap: 6mm; justify-content: center; padding: 0 4mm 10mm; }
            .label { box-shadow: 0 0 0 0.3mm #9aa59f; }
        }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print mx-auto max-w-2xl p-4 text-ink">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-semibold">{{ count($labels) }}টা বক্সের স্টিকার · {{ $pages->count() }}টা লেবেল</p>
            <button type="button" onclick="window.print()" class="btn-primary max-w-48">প্রিন্ট করুন</button>
        </div>
        <details class="mt-3 rounded-xl bg-plate p-3 text-sm">
            <summary class="cursor-pointer font-semibold">প্রিন্ট সেটিং আর কীভাবে লাগাবেন</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>প্রিন্টার: থার্মাল · Paper size <strong>75 × 100 mm</strong> · Margins <strong>None</strong> · Scale <strong>100%</strong> · Headers and footers বন্ধ।</li>
                <li>প্রতি লেবেলে ৩টা স্টিকার — দাগ ধরে কেটে নিন।</li>
                <li>গৃহিণীর বাসায় যে বক্স যার, তার নামের স্টিকার লাগিয়ে অ্যাপে "পিকআপ" চেপে স্ক্যান করুন। অন্যের স্টিকার হলে অ্যাপ সাথে সাথে বলবে।</li>
                <li>অফিসের বক্সে কালো ঘরে "সারি · কলাম" — অফিসে বক্সগুলো সেভাবে সাজিয়ে রাখুন (সারি ১ সামনে, কলাম ১ বাঁ থেকে)। প্রত্যেক কর্মী নোটিফিকেশনে নিজের সারি-কলাম পাবেন।</li>
                <li>বড় QR বক্সের (পিকআপ আর ডেলিভারিতে স্ক্যান)। ডানের ফ্রেমের ছোট QR শুধু কল করার — স্ক্যান করলে সরাসরি গ্রাহককে ফোন হয়।</li>
            </ol>
        </details>
    </div>
    <main class="pages">
        @foreach ($pages as $page)
            <section class="label">
                @foreach ($page as $l)
                    <div class="sticker">
                        <div class="boxqr">
                            {!! $l['qr'] !!}
                            <div class="code">{{ $l['code'] }}</div>
                            <div class="brand">দাওয়াত · {{ $l['box'] }}</div>
                        </div>
                        <div class="mid">
                            <div class="name">{{ $l['name'] }}</div>
                            <div class="tags">
                                @if ($l['spot'])
                                    <span class="tag tag-office">সারি {{ $l['spot']['row'] }} · কলাম {{ $l['spot']['col'] }}</span>
                                @else
                                    <span class="tag tag-meal">{{ $l['meal'] }}</span>
                                @endif
                            </div>
                            <div class="phone">{{ $l['phone'] }}</div>
                            <div class="addr">@if ($l['company'])<b>{{ $l['company'] }}</b> · @endif{{ $l['address'] }}</div>
                        </div>
                        @if ($l['call'])
                            <div class="call">{!! $l['call'] !!}<small>স্ক্যান = কল</small></div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
