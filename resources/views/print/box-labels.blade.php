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
        /* Three levels: the name (and the office spot) first; then the box QR with the address;
           then, set apart in its own frame, the call QR beside the phone number. */
        .sticker { height: 50mm; box-sizing: border-box; padding: 2.4mm 2.8mm 2.2mm; display: grid; grid-template-rows: auto 1fr; overflow: hidden; font-family: 'Noto Sans Bengali', sans-serif; }
        .head { display: flex; align-items: center; justify-content: space-between; gap: 2mm; padding-bottom: 1.3mm; border-bottom: .35mm solid #000; min-width: 0; }
        .name { font: 700 13.5pt/1.15 'Noto Serif Bengali', serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        .tag { flex: none; font: 700 9.5pt/1 'Noto Serif Bengali', serif; padding: 1mm 1.8mm; border-radius: 1.3mm; white-space: nowrap; }
        .tag-office { background: #000; color: #fff; }
        .tag-meal { border: .35mm solid #000; font-size: 8.5pt; }
        .body { display: grid; grid-template-columns: 23mm 1fr; gap: 2.6mm; padding-top: 1.8mm; min-height: 0; }
        .boxqr svg { width: 23mm; height: 23mm; display: block; shape-rendering: crispEdges; }
        .code { font: 700 7pt/1.2 ui-monospace, 'DejaVu Sans Mono', monospace; text-align: center; margin-top: .6mm; letter-spacing: .02em; }
        .brand { font: 700 7.5pt/1.2 'Noto Serif Bengali', serif; text-align: center; }
        .right { display: flex; flex-direction: column; min-width: 0; }
        .company { font-weight: 700; font-size: 7.5pt; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .addr { font-size: 8pt; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .call { margin-top: auto; display: flex; align-items: center; gap: 1.8mm; border: .3mm solid #000; border-radius: 1.6mm; padding: .9mm 1.6mm .9mm .9mm; }
        .call svg { width: 12mm; height: 12mm; display: block; flex: none; shape-rendering: crispEdges; }
        .call small { display: block; font-size: 6.5pt; line-height: 1.2; }
        .phone { font: 700 9.5pt/1.2 ui-monospace, 'DejaVu Sans Mono', monospace; letter-spacing: .02em; }
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
                <li>অফিসের বক্সে কালো ঘরে "সারি · কলাম" — অফিসে বক্সগুলো সেভাবে সাজিয়ে রাখুন (সারি ১ সামনে, কলাম ১ বাঁ থেকে)। প্রত্যেক কর্মী নোটিফিকেশনে নিজের সারি-কলাম পাবেন।</li>
                <li>বড় QR বক্সের (পিকআপ আর ডেলিভারিতে স্ক্যান)। ফ্রেমের ভেতরের ছোট QR শুধু কল করার — স্ক্যান করলে সরাসরি গ্রাহককে ফোন হয়।</li>
            </ol>
        </details>
    </div>
    <main class="pages">
        @foreach ($pages as $page)
            <section class="label">
                @foreach ($page as $l)
                    <div class="sticker">
                        <div class="head">
                            <div class="name">{{ $l['name'] }}</div>
                            @if ($l['spot'])
                                <div class="tag tag-office">সারি {{ $l['spot']['row'] }} · কলাম {{ $l['spot']['col'] }}</div>
                            @else
                                <div class="tag tag-meal">{{ $l['meal'] }}</div>
                            @endif
                        </div>
                        <div class="body">
                            <div class="boxqr">
                                {!! $l['qr'] !!}
                                <div class="code">{{ $l['code'] }}</div>
                                <div class="brand">দাওয়াত · {{ $l['box'] }}</div>
                            </div>
                            <div class="right">
                                @if ($l['company'])<div class="company">{{ $l['company'] }}</div>@endif
                                <div class="addr">{{ $l['address'] }}</div>
                                @if ($l['call'])
                                    <div class="call">
                                        {!! $l['call'] !!}
                                        <div><small>কল করতে স্ক্যান করুন</small><div class="phone">{{ $l['phone'] }}</div></div>
                                    </div>
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
