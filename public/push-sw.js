/* F37: shows Dawat notifications and opens the right screen on tap. Loaded by sw.js. */
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'দাওয়াত', body: event.data ? event.data.text() : '' };
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'দাওয়াত', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/favicon-48.png',
            tag: data.tag || undefined,
            renotify: Boolean(data.tag),
            lang: 'bn',
            data: { url: data.url || '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            for (const w of wins) {
                if (w.url.startsWith(self.location.origin) && 'focus' in w) {
                    w.navigate(url);
                    return w.focus();
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});
