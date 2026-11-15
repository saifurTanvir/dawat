<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>বক্স স্টিকার — {{ $batch }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /* F65: one 75 × 100 mm thermal label = 4 stickers, 2 across, 2 down. Pure black only: thermal heads cannot print grey. */
        @page { size: 75mm 100mm; margin: 0; }
        html, body { margin: 0; }
        .label { width: 75mm; height: 100mm; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; box-sizing: border-box; overflow: hidden; break-after: page; page-break-after: always; background: #fff; position: relative; }
        .label:last-child { break-after: auto; page-break-after: auto; }
        /* Cut guides: a short black dash line through the middle, both ways. */
        .label::before, .label::after { content: ''; position: absolute; }
        .label::before { left: 37.5mm; top: 2mm; bottom: 2mm; border-left: 0.25mm dashed #000; }
        .label::after { top: 50mm; left: 2mm; right: 2mm; border-top: 0.25mm dashed #000; }
        .sticker { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5mm; color: #000; }
        .sticker svg { width: 33mm; height: 33mm; display: block; shape-rendering: crispEdges; }
        .sticker .brand { font-family: 'Noto Serif Bengali', serif; font-weight: 700; font-size: 12pt; line-height: 1.1; margin-top: 0.8mm; }
        .sticker .code { font-family: ui-monospace, 'DejaVu Sans Mono', monospace; font-weight: 700; font-size: 10.5pt; letter-spacing: 0.04em; line-height: 1; margin-top: 0.8mm; }
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
            <p class="font-semibold">{{ count($stickers) }}টা স্টিকার · {{ $pages->count() }}টা লেবেল · ব্যাচ {{ $batch }}</p>
            <button type="button" onclick="window.print()" class="btn-primary max-w-48">প্রিন্ট করুন</button>
        </div>
        <details class="mt-3 rounded-xl bg-plate p-3 text-sm" open>
            <summary class="cursor-pointer font-semibold">প্রথমবার প্রিন্টের আগে প্রিন্ট সেটিং</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>প্রিন্টার (Destination): আপনার থার্মাল প্রিন্টার।</li>
                <li>Paper size: <strong>75 × 100 mm</strong> (না থাকলে প্রিন্টারের ড্রাইভারে এই মাপ যোগ করুন)।</li>
                <li>More settings → Margins: <strong>None</strong> · Scale: <strong>Default / 100%</strong>।</li>
                <li><strong>Headers and footers</strong> বন্ধ রাখুন।</li>
                <li>প্রথমে ১টা লেবেল প্রিন্ট করে ফোনে স্ক্যান করে দেখুন, তারপর বাকিগুলো।</li>
            </ol>
            <p class="mt-2 text-muted">প্রতি লেবেলে ৪টা স্টিকার; মাঝের দাগ ধরে কেটে নিন। <a class="text-leaf underline" href="?paper=a4">A4 কাগজে প্রিন্ট করতে চাইলে</a></p>
        </details>
    </div>
    <main class="pages">
        @foreach ($pages as $page)
            <section class="label">
                @foreach ($page as $s)
                    <div class="sticker">
                        {!! $s['svg'] !!}
                        <p class="brand">দাওয়াত</p>
                        <p class="code">{{ $s['code'] }}</p>
                    </div>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
