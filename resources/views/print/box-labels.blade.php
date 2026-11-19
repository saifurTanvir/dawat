<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>বক্সের স্টিকার — {{ count($labels) }}টা</title>
    @vite(['resources/css/app.css'])
    <style>
        /* F79: one 75 × 100 mm thermal label = two stickers, 75 × 50 mm each. Pure black. */
        @page { size: 75mm 100mm; margin: 0; }
        html, body { margin: 0; }
        .label { width: 75mm; height: 100mm; display: grid; grid-template-rows: 1fr 1fr; box-sizing: border-box; overflow: hidden; break-after: page; page-break-after: always; background: #fff; position: relative; color: #000; }
        .label:last-child { break-after: auto; page-break-after: auto; }
        .label::after { content: ''; position: absolute; top: 50mm; left: 2mm; right: 2mm; border-top: 0.25mm dashed #000; }
        .sticker { display: grid; grid-template-columns: 26mm 1fr; gap: 1.5mm; padding: 2.5mm 2.5mm 2mm; box-sizing: border-box; height: 50mm; overflow: hidden; }
        .box-qr svg { width: 26mm; height: 26mm; display: block; shape-rendering: crispEdges; }
        .code { font: 700 8pt ui-monospace, 'DejaVu Sans Mono', monospace; text-align: center; margin-top: .5mm; letter-spacing: .03em; }
        .brand { font: 700 9pt 'Noto Serif Bengali', serif; text-align: center; margin-top: .5mm; }
        .info { display: flex; flex-direction: column; min-width: 0; font-family: 'Noto Sans Bengali', sans-serif; }
        .name { font-family: 'Noto Serif Bengali', serif; font-weight: 700; font-size: 11pt; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .phone { font: 700 10pt ui-monospace, 'DejaVu Sans Mono', monospace; margin-top: .3mm; }
        .addr { font-size: 7.5pt; line-height: 1.25; margin-top: .6mm; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .bottom { margin-top: auto; display: flex; align-items: flex-end; justify-content: space-between; gap: 1mm; }
        .spot { font-family: 'Noto Serif Bengali', serif; font-weight: 700; font-size: 12pt; line-height: 1.15; white-space: nowrap; border: .4mm solid #000; border-radius: 1.5mm; padding: .6mm 1.2mm; }
        .spot small { display: block; font-size: 6.5pt; font-weight: 600; }
        .box { font-size: 7pt; line-height: 1.2; }
        .call { text-align: center; font-size: 6pt; line-height: 1; flex: none; }
        .call svg { width: 14mm; height: 14mm; display: block; shape-rendering: crispEdges; }
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
                <li>প্রতি লেবেলে ২টা স্টিকার — মাঝের দাগ ধরে কেটে নিন।</li>
                <li>গৃহিণীর বাসায় যে বক্স যার, তার নামের স্টিকার লাগিয়ে অ্যাপে "পিকআপ" চেপে স্ক্যান করুন। অন্যের স্টিকার হলে অ্যাপ সাথে সাথে বলবে।</li>
                <li>অফিসের বক্সে "সারি · কলাম" লেখা — অফিসে বক্সগুলো সেভাবে সাজিয়ে রাখুন (সারি ১ সামনে, কলাম ১ বাঁ থেকে)। প্রত্যেক কর্মী নোটিফিকেশনে নিজের সারি-কলাম পাবেন।</li>
                <li>ডান কোণের ছোট QR স্ক্যান করলে সরাসরি গ্রাহককে ফোন হয়।</li>
            </ol>
        </details>
    </div>
    <main class="pages">
        @foreach ($pages as $page)
            <section class="label">
                @foreach ($page as $l)
                    <div class="sticker">
                        <div class="box-qr">
                            {!! $l['qr'] !!}
                            <div class="code">{{ $l['code'] }}</div>
                            <div class="brand">দাওয়াত</div>
                        </div>
                        <div class="info">
                            <div class="name">{{ $l['name'] }}</div>
                            <div class="phone">{{ $l['phone'] }}</div>
                            <div class="addr">@if ($l['company']){{ $l['company'] }} — @endif{{ $l['address'] }}</div>
                            <div class="bottom">
                                @if ($l['spot'])
                                    <div class="spot"><small>অফিসে রাখুন</small>সারি {{ $l['spot']['row'] }}<br>কলাম {{ $l['spot']['col'] }}</div>
                                @else
                                    <div class="box">{{ $l['box'] }}<br>{{ $l['kitchen'] }}</div>
                                @endif
                                @if ($l['call'])
                                    <div class="call">{!! $l['call'] !!}কল করুন</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
