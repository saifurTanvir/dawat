<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1E5B3F">
    @include('partials.meta')

    <link rel="manifest" href="/build/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="দাওয়াত">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        @isset($intro)
            {{-- Read by search engines and shown until the app starts; Vue replaces it. --}}
            @include('seo.intro')
        @endisset
    </div>
    <noscript>দাওয়াত দেখতে ব্রাউজারে JavaScript চালু করুন।</noscript>
</body>
</html>
