<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR পোস্টার — {{ $qr->label }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none; } body { background: #fff; } }
    </style>
</head>
<body class="bg-steel">
    <main class="mx-auto flex min-h-dvh max-w-[180mm] flex-col items-center justify-center bg-plate px-10 py-12 text-center">
        <p class="font-serif text-6xl font-bold text-leaf">দাওয়াত</p>
        <p class="mt-3 text-2xl">দুপুরে বাইরের খাবার নয়, পাড়ার ঘরের রান্না</p>

        <div class="mt-10 w-[110mm] max-w-full rounded-[var(--radius-plate)] border-4 border-leaf p-4 [&>svg]:h-auto [&>svg]:w-full">
            {!! $svg !!}
        </div>

        <p class="mt-8 text-2xl font-semibold">ফোনের ক্যামেরায় স্ক্যান করুন</p>
        <p class="mt-2 text-xl text-muted">আজ {{ $qr->area->name }}-এ গৃহিণীরা কী রান্না করেছেন দেখুন</p>
        <p class="mt-6 text-lg tracking-wide text-muted">{{ $qr->url() }}</p>

        <button type="button" onclick="window.print()" class="no-print btn-primary mt-10 max-w-xs">প্রিন্ট করুন</button>
    </main>
</body>
</html>
