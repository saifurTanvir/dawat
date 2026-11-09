{{-- F31: everything search engines and chat apps read about a page. --}}
<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['robots'] ?? 'index,follow' }}">
@if (! empty($meta['canonical']))
    <link rel="canonical" href="{{ $meta['canonical'] }}">
@endif
@if (config('dawat.seo.google_verification'))
    <meta name="google-site-verification" content="{{ config('dawat.seo.google_verification') }}">
@endif

{{-- Link previews on WhatsApp, Facebook, Messenger --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ \App\Support\Seo::SITE_NAME }}">
<meta property="og:locale" content="bn_BD">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $meta['url'] }}">
@if ($meta['image'])
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
@if (! empty($jsonLd))
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
