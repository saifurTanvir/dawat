import { api } from '@/lib/api';

/** F37: free phone notifications through the browser / installed app. */
export function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export function pushPermission() {
    return pushSupported() ? Notification.permission : 'unsupported';
}

function keyBytes(base64) {
    const pad = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

/** Is this device already subscribed? */
export async function pushActive() {
    if (!pushSupported()) return false;
    const reg = await navigator.serviceWorker.getRegistration('/');
    return Boolean(reg && (await reg.pushManager.getSubscription()));
}

/** Asks permission, subscribes, and tells the server. Returns '' or a Bangla error. */
export async function enablePush() {
    if (!pushSupported()) return 'এই ফোনের ব্রাউজারে নোটিফিকেশন চলে না। দাওয়াত অ্যাপ ইনস্টল করে আবার চেষ্টা করুন।';
    const { data } = await api.get('/push/key');
    if (!data.enabled || !data.key) return 'নোটিফিকেশন এখনো চালু করা হয়নি। একটু পরে চেষ্টা করুন।';

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return 'অনুমতি দেওয়া হয়নি। ফোনের সেটিংস থেকে দাওয়াতের নোটিফিকেশন চালু করুন।';

    const reg = (await navigator.serviceWorker.getRegistration('/')) || (await navigator.serviceWorker.register('/sw.js', { scope: '/' }));
    await navigator.serviceWorker.ready;
    const sub = (await reg.pushManager.getSubscription())
        || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(data.key) }));
    await api.post('/push/subscribe', sub.toJSON());
    return '';
}

export async function disablePush() {
    const reg = await navigator.serviceWorker.getRegistration('/');
    const sub = reg && (await reg.pushManager.getSubscription());
    if (!sub) return;
    await api.delete('/push/subscribe', { data: { endpoint: sub.endpoint } });
    await sub.unsubscribe();
}
