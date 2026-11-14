{{-- F62: Leaflet (served from /vendor/leaflet) and the location box's helper, loaded once for the admin panel. --}}
    <style>
        .dl-box { display: grid; gap: .5rem; }
        .dl-row { display: flex; flex-wrap: wrap; gap: .5rem; }
        .dl-input { width: 100%; border: 1px solid rgb(209 213 219); border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem; background: #fff; }
        .dl-grow { flex: 1 1 14rem; width: auto; min-width: 0; }
        .dl-btn { border: 1px solid rgb(209 213 219); border-radius: .5rem; padding: .45rem .8rem; font-size: .875rem; font-weight: 600; background: #fff; white-space: nowrap; }
        .dl-btn:disabled { opacity: .6; }
        .dl-primary { background: rgb(30 91 63); border-color: rgb(30 91 63); color: #fff; }
        .dl-msg { color: rgb(185 28 28); font-size: .875rem; }
        .dl-results { border: 1px solid rgb(229 231 235); border-radius: .5rem; font-size: .875rem; }
        .dl-results li + li { border-top: 1px solid rgb(229 231 235); }
        .dl-results button { display: block; width: 100%; text-align: start; padding: .5rem .75rem; }
        .dl-results button:hover { background: rgb(249 250 251); }
        .dl-map { height: 14rem; width: 100%; border: 1px solid rgb(229 231 235); border-radius: .5rem; overflow: hidden; }
        .dl-hint { font-size: .75rem; color: rgb(107 114 128); }
        .dl-hint a { color: rgb(30 91 63); font-weight: 600; }
        .dark .dl-input, .dark .dl-btn:not(.dl-primary) { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: inherit; }
    </style>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
    <script>
        window.dawatLocation = window.dawatLocation || function ({ state, searchUrl }) {
            const COORD = /(-?\d{1,2}\.\d{2,})\s*,\s*(-?\d{2,3}\.\d{2,})/;
            return {
                state, searchUrl, query: '', results: [], message: '', busy: false, point: null, map: null, marker: null,
                init() { this.fromText(); },
                fromText() {
                    const m = decodeURIComponent(String(this.state || '')).match(COORD);
                    if (m) this.show(parseFloat(m[1]), parseFloat(m[2]));
                    else if (String(this.state || '').startsWith('http')) this.message = '';
                },
                set(lat, lng) {
                    this.state = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    this.show(lat, lng);
                },
                show(lat, lng) {
                    this.point = { lat, lng };
                    this.$nextTick(() => {
                        if (!window.L) return;
                        if (!this.map) {
                            this.map = L.map(this.$refs.map).setView([lat, lng], 17);
                            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(this.map);
                            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                            this.marker.on('dragend', () => { const p = this.marker.getLatLng(); this.set(p.lat, p.lng); });
                            this.map.on('click', (e) => { this.marker.setLatLng(e.latlng); this.set(e.latlng.lat, e.latlng.lng); });
                        } else {
                            this.marker.setLatLng([lat, lng]);
                            this.map.setView([lat, lng], Math.max(this.map.getZoom(), 16));
                        }
                        setTimeout(() => this.map.invalidateSize(), 50);
                    });
                },
                here() {
                    this.message = '';
                    if (!navigator.geolocation) { this.message = 'এই ব্রাউজারে লোকেশন নেওয়া যায় না।'; return; }
                    this.busy = 'here';
                    navigator.geolocation.getCurrentPosition(
                        (pos) => { this.busy = false; this.set(pos.coords.latitude, pos.coords.longitude); },
                        () => { this.busy = false; this.message = 'লোকেশন পাওয়া যায়নি। ব্রাউজারে লোকেশনের অনুমতি দিন, বা লিংক/ঠিকানা দিন।'; },
                        { enableHighAccuracy: true, timeout: 15000 },
                    );
                },
                async search() {
                    this.message = ''; this.results = [];
                    this.busy = 'search';
                    try {
                        const r = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.query)}`, { headers: { Accept: 'application/json' } });
                        const json = await r.json();
                        this.results = json.data || [];
                        if (this.results.length === 1) this.pick(this.results[0]);
                        if (!this.results.length) this.message = 'খুঁজে পাওয়া যায়নি। কাছের বড় জায়গার নাম লিখুন, বা গুগল ম্যাপের লিংক দিন।';
                    } catch { this.message = 'এখন খোঁজা যাচ্ছে না।'; }
                    this.busy = false;
                },
                pick(r) { this.results = []; this.set(r.lat, r.lng); },
            };
        };
    </script>
