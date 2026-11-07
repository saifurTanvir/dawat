<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Sans'; font-weight: 400; src: url('{{ $fonts['noto-sans-bengali-bengali-400-normal'] }}') format('woff2'); unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1; }
    @font-face { font-family: 'Sans'; font-weight: 400; src: url('{{ $fonts['noto-sans-bengali-latin-400-normal'] }}') format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
    @font-face { font-family: 'Sans'; font-weight: 600; src: url('{{ $fonts['noto-sans-bengali-bengali-600-normal'] }}') format('woff2'); unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1; }
    @font-face { font-family: 'Sans'; font-weight: 600; src: url('{{ $fonts['noto-sans-bengali-latin-600-normal'] }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F, U+20AC, U+2122; }
    @font-face { font-family: 'Serif'; font-weight: 700; src: url('{{ $fonts['noto-serif-bengali-bengali-700-normal'] }}') format('woff2'); unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+200C-200D, U+25CC; }
    @font-face { font-family: 'Serif'; font-weight: 700; src: url('{{ $fonts['noto-serif-bengali-latin-700-normal'] }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }

    * { box-sizing: border-box; margin: 0; }
    html, body { width: 1080px; height: 1350px; }
    body { font-family: 'Sans', sans-serif; color: #17211c; background: #eef2ef; overflow: hidden; }

    .photo { position: relative; height: 780px; background: #dfe9e2; }
    .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .photo .empty { height: 100%; display: flex; align-items: center; justify-content: center; font: 700 64px 'Serif'; color: #1e5b3f; }
    .meal { position: absolute; top: 36px; left: 36px; background: rgba(23,33,28,.78); color: #fff; font-size: 30px; padding: 6px 22px; border-radius: 999px; }
    .price { position: absolute; right: 36px; bottom: -44px; background: #fbefcc; border: 4px solid #fff; border-radius: 22px; padding: 10px 28px; font: 600 64px 'Sans'; line-height: 1.1; }
    .price small { font-size: 30px; font-weight: 400; color: #56645c; }

    .body { padding: 34px 48px 0; height: 570px; display: flex; flex-direction: column; }
    h1 { font: 700 60px/1.25 'Serif'; max-width: 760px; }
    .who { margin-top: 12px; font-size: 32px; color: #56645c; }
    .tags { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px; }
    .tags span { background: #dfe9e2; color: #143f2c; font-size: 28px; padding: 6px 20px; border-radius: 999px; }
    .note { margin-top: 22px; border-left: 8px solid #1e5b3f; padding-left: 22px; font-size: 32px; line-height: 1.45; }

    .foot { margin-top: auto; padding: 22px 0 36px; display: flex; align-items: center; justify-content: space-between; border-top: 2px solid #d3dbd5; }
    .brand b { display: block; font: 700 64px/1 'Serif'; color: #1e5b3f; }
    .brand span { display: block; margin-top: 10px; font-size: 28px; color: #56645c; }
    .scan { display: flex; align-items: center; gap: 18px; text-align: right; font-size: 24px; color: #56645c; }
    .scan .qr { width: 150px; height: 150px; background: #fff; padding: 6px; border-radius: 12px; }
    .scan .qr svg { width: 100%; height: 100%; display: block; }
    .scan strong { display: block; color: #17211c; font-size: 28px; }
</style>
</head>
<body>
    <div class="photo">
        @if ($photo)
            <img src="{{ $photo }}" alt="">
        @else
            <div class="empty">দাওয়াত</div>
        @endif
        <span class="meal">{{ $meal }}</span>
        <span class="price">৳{{ $price }}<small>/প্লেট</small></span>
    </div>

    <div class="body">
        <h1>{{ $title }}</h1>
        <p class="who">{{ $kitchen }}@if ($area) · {{ $area }}@endif</p>

        @if ($tags)
            <div class="tags">
                @foreach ($tags as $tag)<span>{{ $tag }}</span>@endforeach
            </div>
        @endif

        @if ($note)
            <p class="note">{{ $note }}</p>
        @endif

        <div class="foot">
            <div class="brand">
                <b>দাওয়াত</b>
                <span>পাড়ার ঘরের খাবার</span>
            </div>
            <div class="scan">
                <div>
                    <strong>স্ক্যান করে দেখুন</strong>
                    {{ $url }}
                </div>
                <div class="qr">{!! $qr !!}</div>
            </div>
        </div>
    </div>
</body>
</html>
