/* F37: shows Dawat notifications and opens the right screen on tap. Loaded by sw.js.
   F66: says which part of the app it is about, opens through /n/{id} so the
   in-app 🔔 list marks it read, and keeps the app-icon number = unread count. */
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'দাওয়াত', body: event.data ? event.data.text() : '' };
    }
    const lines = [data.body || ''];
    if (data.section) lines.push(data.section + ' · খুলতে চাপুন');

    const jobs = [
        self.registration.showNotification(data.title || 'দাওয়াত', {
            body: lines.filter(Boolean).join('\n'),
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-96.png',
            tag: data.tag || undefined,
            renotify: Boolean(data.tag),
            lang: 'bn',
            data: { url: data.open || data.url || '/' },
        }),
    ];
    if (typeof data.unread === 'number' && self.navigator && 'setAppBadge' in self.navigator) {
        jobs.push((data.unread > 0 ? self.navigator.setAppBadge(data.unread) : self.navigator.clearAppBadge()).catch(() => {}));
    }
    event.waitUntil(Promise.all(jobs));
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
