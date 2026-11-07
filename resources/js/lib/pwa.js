/**
 * Registers the service worker in production builds only, so `npm run dev`
 * never serves stale cached assets.
 */
export function registerServiceWorker() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // The app works without offline support; nothing to tell the user.
        });
    });
}
