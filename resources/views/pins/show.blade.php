@php($k = $link->kitchen)
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1E5B3F">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.meta')
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <style>
        .pin-why { border: 2px solid #e8a90c; background: #fff8e6; border-radius: 1rem; padding: 1rem 1.1rem; }
        .pin-why h2 { font-size: 1.2rem; font-weight: 800; color: #7a4b00; }
        .pin-why p { margin-top: .5rem; line-height: 1.6; }
        .pin-why b { color: #143f2c; }
        .pin-btn { display: block; width: 100%; border-radius: .9rem; padding: .95rem 1rem; font-weight: 700; font-size: 1.05rem; text-align: center; }
        .pin-primary { background: #1e5b3f; color: #fff; }
        .pin-quiet { background: #fff; border: 2px solid #1e5b3f; color: #1e5b3f; }
        .pin-map { height: 20rem; border-radius: 1rem; overflow: hidden; border: 1px solid #e5e7eb; }
        .pin-err { color: #b91c1c; font-weight: 600; }
        .pin-input { width: 100%; border: 1px solid #d1d5db; border-radius: .7rem; padding: .7rem .9rem; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main class="mx-auto max-w-lg px-4 pt-5 pb-12">
    <header class="flex items-center gap-2">
        <img src="/brand/mark.png" alt="" width="63" height="32" class="h-8 w-auto">
        <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="h-8 w-auto">
    </header>

    <h1 class="mt-5 font-serif text-2xl font-bold">{{ $k->user?->name ? $k->user->name.' আপু, ' : '' }}আপনার বাসার লোকেশন দিন</h1>
    <p class="mt-1 text-sm text-gray-600">{{ $k->display_name }}{{ $k->area ? ' · '.$k->area->name : '' }}</p>

    <section class="pin-why mt-4">
        <h2>⚠️ লোকেশন ছাড়া আপনাকে অনবোর্ড করা যাচ্ছে না</h2>
        <p>আপনার রান্নাঘর থেকে <b>আধা থেকে ১ কিলোমিটারের মধ্যে</b> যে গ্রাহকেরা আছেন, <b>শুধু তাঁদেরই</b> আপনার সাথে জুড়ে দেওয়া হয়। আমাদের শাহিদার আপনার <b>বাসার গেট থেকে</b> খাবারের বক্স আনেন।</p>
        <p><b>বাসার লোকেশন না পেলে আপনাকে গ্রাহক দিতে পারব না, আপনার খাবারও আনতে পারব না।</b> তাই এটা ১ মিনিটে দিয়ে দিন।</p>
        <p class="text-sm">🔒 আপনার লোকেশন কোনো গ্রাহক দেখবেন না, আপনার নাম-ছবিও না।</p>
    </section>

    @if ($state === 'expired')
        <p class="mt-6 pin-err">এই লিংকের মেয়াদ শেষ। দাওয়াতের WhatsApp-এ (01685284290) জানান, নতুন লিংক পাঠিয়ে দেব।</p>
    @else
        <div id="done" class="mt-6 rounded-2xl bg-green-50 p-4" @if ($state === 'new') hidden @endif>
            <p class="text-lg font-bold text-green-800">✅ ধন্যবাদ! আপনার লোকেশন পেয়েছি।</p>
            <p id="done-review" class="mt-1" @if ($state !== 'review') hidden @endif>লোকেশনটা আপনার এলাকা থেকে একটু দূরে পড়েছে, দাওয়াতের টিম মিলিয়ে দেখে জানাবে।</p>
            <button type="button" class="mt-3 underline" onclick="pin.restart()">ভুল হয়েছে? আবার দিন</button>
        </div>

        <div id="ask" class="mt-6 space-y-3" @if ($state !== 'new') hidden @endif>
            <p class="text-lg font-bold">আপনি কি এখন বাসায় আছেন?</p>
            <button type="button" class="pin-btn pin-primary" onclick="pin.home()">🏠 হ্যাঁ, বাসায় আছি — এখানকার লোকেশন নিন</button>
            <button type="button" class="pin-btn pin-quiet" onclick="pin.away()">🚶 না, বাইরে আছি — ম্যাপে বাসা দেখাব</button>
            <p id="ask-msg" class="pin-err" hidden></p>
        </div>

        <div id="pick" class="mt-6 space-y-3" hidden>
            <p id="pick-hint" class="font-semibold"></p>
            <div id="search-box" class="flex gap-2" hidden>
                <input id="q" class="pin-input" placeholder="বাসার কাছের বড় জায়গা/রাস্তার নাম লিখুন" onkeydown="if(event.key==='Enter'){event.preventDefault();pin.search()}">
                <button type="button" class="rounded-xl bg-gray-100 px-4 font-semibold" onclick="pin.search()">খুঁজুন</button>
            </div>
            <ul id="results" class="space-y-1"></ul>
            <div id="map" class="pin-map"></div>
            <p class="text-sm text-gray-600">ম্যাপে নীল পিনটা টেনে বা ম্যাপে চেপে ঠিক আপনার বাসার উপর রাখুন।</p>
            <button id="save" type="button" class="pin-btn pin-primary" onclick="pin.save()">✅ এটাই আমার বাসা</button>
            <button type="button" class="pin-btn pin-quiet" onclick="pin.restart()">পেছনে যান</button>
            <p id="pick-msg" class="pin-err" hidden></p>
        </div>
    @endif
</main>

<script>
    const pin = (() => {
        const $ = (id) => document.getElementById(id);
        const center = @json($k->area?->center ? [$k->area->center->lat, $k->area->center->lng] : [23.78, 90.40]);
        const urls = { save: @json(route('pin.store', $link->token)), search: @json(route('pin.search', $link->token)) };
        let map, marker, mode = 'map', accuracy = null;

        const show = (id, on = true) => { const el = $(id); if (el) el.hidden = !on; };
        const err = (id, text) => { const el = $(id); el.textContent = text || ''; el.hidden = !text; };

        function openMap(lat, lng, zoom) {
            show('ask', false); show('done', false); show('pick');
            if (!map) {
                map = L.map('map').setView([lat, lng], zoom);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                map.on('click', (e) => marker.setLatLng(e.latlng));
            } else {
                map.setView([lat, lng], zoom);
                marker.setLatLng([lat, lng]);
            }
            setTimeout(() => map.invalidateSize(), 60);
        }

        return {
            home() {
                err('ask-msg');
                if (!navigator.geolocation) { err('ask-msg', 'এই ফোনে লোকেশন নেওয়া যাচ্ছে না। "বাইরে আছি" চেপে ম্যাপে দিন।'); return; }
                err('ask-msg', 'লোকেশন নিচ্ছি… ফোন অনুমতি চাইলে "Allow" চাপুন।');
                navigator.geolocation.getCurrentPosition((p) => {
                    accuracy = p.coords.accuracy;
                    if (accuracy > 100) {
                        err('ask-msg', `ফোনের লোকেশন ঠিকমতো আসেনি (প্রায় ${Math.round(accuracy)} মিটার এদিক-ওদিক)। জানালার কাছে গিয়ে আবার চাপুন, বা "বাইরে আছি" চেপে ম্যাপে বাসা দেখান।`);
                        return;
                    }
                    err('ask-msg'); mode = 'home';
                    $('pick-hint').textContent = 'আপনার ফোনের লোকেশন। পিনটা ঠিক আপনার বাসার উপর আছে তো? দরকার হলে একটু সরিয়ে দিন।';
                    show('search-box', false);
                    openMap(p.coords.latitude, p.coords.longitude, 18);
                }, () => err('ask-msg', 'লোকেশন পাওয়া যায়নি। ফোনের লোকেশন (GPS) চালু করে "Allow" দিন, অথবা "বাইরে আছি" চেপে ম্যাপে দিন।'),
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
            },
            away() {
                mode = 'map'; accuracy = null;
                $('pick-hint').textContent = 'বাসার কাছের রাস্তা/মসজিদ/দোকানের নাম লিখে খুঁজুন, তারপর পিনটা আপনার বাসার উপর রাখুন।';
                show('search-box');
                openMap(center[0], center[1], 15);
            },
            async search() {
                err('pick-msg'); $('results').innerHTML = '';
                const q = $('q').value.trim();
                if (q.length < 3) return;
                try {
                    const r = await fetch(`${urls.search}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                    const list = (await r.json()).data || [];
                    if (!list.length) { err('pick-msg', 'খুঁজে পাওয়া যায়নি। কাছের বড় জায়গার নাম লিখুন, বা ম্যাপ সরিয়ে নিজে খুঁজুন।'); return; }
                    for (const it of list.slice(0, 5)) {
                        const li = document.createElement('li');
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'w-full rounded-lg bg-gray-50 px-3 py-2 text-left text-sm';
                        b.textContent = it.label;
                        b.onclick = () => { $('results').innerHTML = ''; openMap(it.lat, it.lng, 17); };
                        li.appendChild(b); $('results').appendChild(li);
                    }
                    if (list.length === 1) openMap(list[0].lat, list[0].lng, 17);
                } catch { err('pick-msg', 'এখন খোঁজা যাচ্ছে না। ম্যাপ সরিয়ে নিজে খুঁজুন।'); }
            },
            async save() {
                err('pick-msg');
                const p = marker.getLatLng();
                $('save').disabled = true;
                try {
                    const r = await fetch(urls.save, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ lat: p.lat, lng: p.lng, mode, accuracy }),
                    });
                    const json = await r.json();
                    if (!r.ok) { err('pick-msg', Object.values(json.errors || {}).flat()[0] || 'রাখা যায়নি, আবার চেষ্টা করুন।'); return; }
                    show('pick', false); show('done'); show('done-review', json.result === 'review');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } catch { err('pick-msg', 'ইন্টারনেট সমস্যা, আবার চাপুন।'); }
                finally { $('save').disabled = false; }
            },
            restart() { show('pick', false); show('done', false); show('ask'); err('ask-msg'); },
        };
    })();
</script>
</body>
</html>
