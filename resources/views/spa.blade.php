<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1E5B3F">
    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">

    {{-- Link previews on WhatsApp, Facebook, Messenger --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="bn_BD">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    @if ($meta['image'])
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif

    <link rel="manifest" href="/build/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
    <noscript>দাওয়াত দেখতে ব্রাউজারে JavaScript চালু করুন।</noscript>
</body>
</html>
