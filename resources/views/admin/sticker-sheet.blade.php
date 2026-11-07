<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>বক্স স্টিকার — {{ $batch }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 8mm; }
        .sheet { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; }
        .sticker { height: 35mm; border: 0.2mm dashed #bbb; display: flex; align-items: center; gap: 2mm; padding: 2mm 3mm; break-inside: avoid; }
        .sticker svg { width: 29mm; height: 29mm; flex: none; }
        .sticker:nth-child(32n) { break-after: page; }
        @media print { .no-print { display: none; } body { background: #fff; } }
    </style>
</head>
<body class="bg-plate">
    <div class="no-print mx-auto flex max-w-3xl items-center justify-between gap-4 p-4">
        <p>{{ count($stickers) }}টা স্টিকার · ব্যাচ {{ $batch }}. স্টিকার কাগজে A4-এ প্রিন্ট করুন, দাগ ধরে কাটুন।</p>
        <button type="button" onclick="window.print()" class="btn-primary max-w-48">প্রিন্ট করুন</button>
    </div>
    <main class="sheet">
        @foreach ($stickers as $s)
            <div class="sticker">
                {!! $s['svg'] !!}
                <div class="leading-tight">
                    <p class="font-serif text-lg font-bold text-leaf">দাওয়াত</p>
                    <p class="text-[9pt] text-muted">ঘরের খাবার</p>
                    <p class="mt-1 font-mono text-[10pt] font-semibold tracking-wider">{{ $s['code'] }}</p>
                </div>
            </div>
        @endforeach
    </main>
</body>
</html>
