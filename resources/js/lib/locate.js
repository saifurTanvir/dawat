/**
 * F112: the phone's location, given a few seconds to settle.
 *
 * The first reading indoors often comes from Wi-Fi or the mobile tower and can be
 * 100 m or more off. So this watches for up to `maxWait` ms, keeps the most
 * accurate reading, and stops early once one is good enough.
 *
 * Resolves { lat, lng, accuracy } (accuracy in metres), or rejects with the
 * browser's error when no reading came at all.
 */
export function locateBest({ good = 20, maxWait = 12000 } = {}) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('unsupported'));
            return;
        }
        let best = null;
        let done = false;
        let watchId = null;
        let timer = null;

        const finish = (error) => {
            if (done) return;
            done = true;
            clearTimeout(timer);
            if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            if (best) resolve(best);
            else reject(error ?? new Error('timeout'));
        };

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const reading = {
                    lat: Number(pos.coords.latitude.toFixed(6)),
                    lng: Number(pos.coords.longitude.toFixed(6)),
                    accuracy: Math.round(pos.coords.accuracy ?? 9999),
                };
                if (!best || reading.accuracy < best.accuracy) best = reading;
                if (best.accuracy <= good) finish();
            },
            (error) => {
                // Permission refused: stop now. Other errors: keep waiting for a reading.
                if (error.code === 1) finish(error);
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: maxWait },
        );
        timer = setTimeout(() => finish(), maxWait);
    });
}
