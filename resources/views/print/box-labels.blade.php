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
        /* Width in fifths: the box QR (handover scan) takes the left 2/5. The right 3/5 has the
           name with the meal or office spot on top, a rule under it, then the address (2/3)
           and the call QR (1/3). */
        .sticker { height: 33.33mm; box-sizing: border-box; padding: 1.6mm 2mm; display: grid; grid-template-columns: 2fr 3fr; overflow: hidden; font-family: 'Noto Sans Bengali', sans-serif; }
        .boxqr { display: flex; flex-direction: column; align-items: center; justify-content: center; border-right: .3mm solid #000; padding-right: 1.5mm; }
        .boxqr svg { width: 24.5mm; height: 24.5mm; display: block; shape-rendering: crispEdges; }
        .code { font: 700 6pt/1.1 ui-monospace, 'DejaVu Sans Mono', monospace; text-align: center; margin-top: .3mm; white-space: nowrap; }
        .right { display: grid; grid-template-rows: auto 1fr; min-width: 0; padding-left: 2mm; }
        .head { height: 6.4mm; box-sizing: border-box; display: flex; align-items: center; justify-content: space-between; gap: 1.2mm; padding-bottom: .9mm; border-bottom: .3mm solid #000; min-width: 0; }
        .name { font: 700 11.5pt/1.15 'Noto Serif Bengali', serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .tag { flex: none; font: 700 7.5pt/1 'Noto Serif Bengali', serif; padding: .7mm 1.2mm; border-radius: 1mm; white-space: nowrap; }
        .tag-office { background: #000; color: #fff; }
        .tag-meal { border: .3mm solid #000; }
        /* The address runs the full width; the call QR and phone sit in the bottom-right corner
           (a zero-width float pushes them down), so the address wraps around them. */
        .body { height: calc(33.33mm - 3.2mm - 6.4mm); box-sizing: border-box; padding-top: 1.1mm; overflow: hidden; font-size: 7.2pt; line-height: 1.3; }
        .push { float: right; width: 0; height: calc(33.33mm - 3.2mm - 6.4mm - 1.1mm - 15.6mm); }
        .call { float: right; clear: right; width: 15mm; margin-left: 1mm; text-align: center; }
        .call svg { width: 12.5mm; height: 12.5mm; display: block; margin: 0 auto; shape-rendering: crispEdges; }
        .call small { display: block; font: 700 5.6pt/1.1 ui-monospace, 'DejaVu Sans Mono', monospace; margin-top: .3mm; white-space: nowrap; letter-spacing: -.02em; }
        .addr b { font-weight: 700; }
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
                <li>বাঁয়ের বড় QR বক্সের (পিকআপ আর ডেলিভারিতে স্ক্যান)। ডান কোণের ছোট QR (নিচে মোবাইল নম্বর) শুধু কল করার — স্ক্যান করলে সরাসরি গ্রাহককে ফোন হয়।</li>
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
                            <div class="code">{{ $l['code'] }} · {{ $l['box'] }}</div>
                        </div>
                        <div class="right">
                            <div class="head">
                                <div class="name">{{ $l['name'] }}</div>
                                @if ($l['spot'])
                                    <span class="tag tag-office">সারি {{ $l['spot']['row'] }} · কলাম {{ $l['spot']['col'] }}</span>
                                @else
                                    <span class="tag tag-meal">{{ $l['meal'] }}</span>
                                @endif
                            </div>
                            <div class="body">
                                @if ($l['call'])
                                    <span class="push"></span>
                                    <span class="call">{!! $l['call'] !!}<small>{{ $l['phone'] }}</small></span>
                                @endif
                                <span class="addr">@if ($l['company'])<b>{{ $l['company'] }}</b> · @endif{{ $l['address'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
