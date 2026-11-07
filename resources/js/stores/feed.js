import { defineStore } from 'pinia';

const PREFS_KEY = 'dawat.feed';
const QR_KEY = 'dawat.qr';

function read(storage, key) {
    try {
        return JSON.parse(storage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function write(storage, key, value) {
    try {
        if (value == null) storage.removeItem(key);
        else storage.setItem(key, JSON.stringify(value));
    } catch {
        // Storage blocked (private mode): preferences just won't persist.
    }
}

/**
 * Where the buyer is looking from and their filters.
 * The buyer's own coordinates stay in memory only; we remember that they chose
 * "near me", not where they were.
 */
export const useFeedStore = defineStore('feed', {
    state: () => {
        const prefs = read(localStorage, PREFS_KEY) ?? {};
        return {
            mode: prefs.mode ?? null, // 'location' | 'area' | null
            areaSlug: prefs.areaSlug ?? null,
            radius: prefs.radius ?? '3km',
            meal: null,
            position: null, // { lat, lng } — never persisted
            qr: read(sessionStorage, QR_KEY), // { code, area_slug, label }
        };
    },

    actions: {
        persist() {
            write(localStorage, PREFS_KEY, { mode: this.mode, areaSlug: this.areaSlug, radius: this.radius });
        },

        useArea(slug) {
            this.mode = 'area';
            this.areaSlug = slug;
            this.position = null;
            this.persist();
        },

        usePosition(position) {
            this.mode = 'location';
            this.position = position;
            this.persist();
        },

        setRadius(radius) {
            this.radius = radius;
            this.persist();
        },

        rememberQr(qr) {
            this.qr = qr;
            write(sessionStorage, QR_KEY, qr);
            this.useArea(qr.area_slug);
        },

        reset() {
            this.mode = null;
            this.position = null;
            this.persist();
        },

        /** Query params for GET /feed, or null when we still need a place. */
        params() {
            const base = { radius: this.radius, ...(this.meal ? { meal: this.meal } : {}) };
            if (this.mode === 'location' && this.position) return { ...base, ...this.position };
            if (this.mode === 'area' && this.areaSlug) return { ...base, area: this.areaSlug };
            return null;
        },
    },
});

/** Promise wrapper around the browser's geolocation. */
export function currentPosition() {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('unsupported'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: Number(pos.coords.latitude.toFixed(5)), lng: Number(pos.coords.longitude.toFixed(5)) }),
            reject,
            { enableHighAccuracy: false, timeout: 12000, maximumAge: 5 * 60 * 1000 },
        );
    });
}
