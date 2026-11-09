// F35: the admin's "where does this post show" map (Filament post page).
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);

function draw(el) {
    const key = el.dataset.reachMap;
    // Livewire may morph the page after an action: redraw only when the data or the map is gone.
    if (el._dwKey === key && el.querySelector('.leaflet-pane')) return;
    if (el._dwMap) {
        try {
            el._dwMap.remove();
        } catch {
            // Livewire already replaced the map's insides.
        }
        el._dwMap = null;
        delete el._leaflet_id;
    }
    el._dwKey = key;
    const cfg = JSON.parse(key);
    if (!cfg.post) {
        el.textContent = 'এই পোস্টের লোকেশন নেই।';
        return;
    }

    const map = L.map(el, { scrollWheelZoom: false }).setView(cfg.post, 13);
    el._dwMap = map;
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const shapes = [];
    for (const r of cfg.tiers) {
        shapes.push(L.circle(cfg.post, { radius: r, color: '#6b7280', weight: 1, dashArray: '4 6', fill: false }).addTo(map));
    }
    if (cfg.reach_m) {
        shapes.push(L.circle(cfg.post, { radius: cfg.reach_m, color: '#1e5b3f', weight: 2, fillColor: '#1e5b3f', fillOpacity: 0.1 }).addTo(map));
    }
    for (const a of cfg.areas) {
        if (a.extra) {
            shapes.push(L.circle(a.center, { radius: a.radius, color: '#e8a90c', weight: 2, fillColor: '#e8a90c', fillOpacity: 0.18 }).addTo(map));
        }
        const label = a.views ? `${a.name} · ${bn(a.views)} জন` : a.name;
        L.marker(a.center, {
            icon: L.divIcon({
                className: '',
                html: `<span style="font:600 11px/1.2 sans-serif;color:${a.views ? '#143f2c' : '#6b7280'};background:rgba(255,255,255,.85);padding:2px 5px;border-radius:6px;white-space:nowrap">${label}</span>`,
                iconAnchor: [0, 8],
            }),
            keyboard: false,
            interactive: false,
        }).addTo(map);
    }
    L.circleMarker(cfg.post, { radius: 7, color: '#fff', weight: 2, fillColor: '#1e5b3f', fillOpacity: 1 }).addTo(map);

    const bounds = L.featureGroup(shapes).getBounds();
    if (bounds.isValid()) map.fitBounds(bounds.pad(0.05));
}

function drawAll() {
    document.querySelectorAll('[data-reach-map]').forEach(draw);
}

drawAll();
document.addEventListener('livewire:navigated', drawAll);
// Filament re-renders after actions (e.g. "পরিধি বদলান"); redraw new containers.
new MutationObserver(drawAll).observe(document.body, { childList: true, subtree: true });
