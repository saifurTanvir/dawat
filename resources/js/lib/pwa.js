import { reactive } from 'vue';

/**
 * F36: "install as an app" state, shared by the install page and the feed.
 * Chrome on Android fires `beforeinstallprompt`; iPhones need Share → Add to Home Screen.
 */
const ua = navigator.userAgent || '';
export const install = reactive({
    canPrompt: false,
    installed: false,
    standalone: window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true,
    platform: /iphone|ipad|ipod/i.test(ua) ? 'ios' : /android/i.test(ua) ? 'android' : 'other',
    // Facebook, Messenger, WhatsApp and Instagram open links in their own browser, which cannot install.
    inAppBrowser: /FBAN|FBAV|FB_IAB|Instagram|WhatsApp|Line\//i.test(ua),
});

let deferred = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferred = event;
    install.canPrompt = true;
});
window.addEventListener('appinstalled', () => {
    install.installed = true;
    install.canPrompt = false;
    deferred = null;
});

/** Shows the browser's install dialog. Resolves true when the person accepts. */
export async function promptInstall() {
    if (!deferred) return false;
    deferred.prompt();
    const { outcome } = await deferred.userChoice;
    deferred = null;
    install.canPrompt = false;
    if (outcome === 'accepted') install.installed = true;
    return outcome === 'accepted';
}

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
