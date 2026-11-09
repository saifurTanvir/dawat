<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1E5B3F">
    @include('partials.meta')
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @vite(['resources/css/app.css'])
</head>
<body>
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <header class="flex items-center justify-between gap-3 px-4 pt-5 pb-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="দাওয়াত — হোম">
                <img src="/brand/mark.png" alt="" width="63" height="32" class="h-8 w-auto">
                <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="h-8 w-auto">
                <span class="hidden text-sm whitespace-nowrap text-muted min-[480px]:inline">পাড়ার ঘরের খাবার</span>
            </a>
            <a href="{{ url('/') }}" class="btn-quiet -mr-4 min-h-10 whitespace-nowrap">আজকের খাবার</a>
        </header>
        <main class="flex-1 px-4 pt-2">
            <nav aria-label="আপনি এখানে" class="text-sm text-muted">
                <a href="{{ url('/') }}" class="text-leaf">দাওয়াত</a> / {{ $crumb }}
            </nav>
            @yield('content')
            @include('seo.footer')
        </main>
    </div>
</body>
</html>
