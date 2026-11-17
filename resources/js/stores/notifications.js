import { defineStore } from 'pinia';
import { api } from '@/lib/api';

/** F66: the 🔔 list and its unread number; the app-icon number follows it. */
function setIconBadge(n) {
    try {
        if (!('setAppBadge' in navigator)) return;
        (n > 0 ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(() => {});
    } catch {
        /* not supported */
    }
}

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({ unread: 0, items: null }),

    actions: {
        setUnread(n) {
            this.unread = n;
            setIconBadge(n);
        },
        async refreshCount() {
            try {
                const { data } = await api.get('/notifications/unread');
                this.setUnread(data.unread);
            } catch {
                /* offline or signed out: keep the old number */
            }
        },
        async load() {
            const { data } = await api.get('/notifications');
            this.items = data.data;
            this.setUnread(data.unread);
        },
        /** Marks one read and returns where it opens. */
        async open(id) {
            const { data } = await api.post(`/notifications/${id}/read`);
            this.setUnread(data.unread);
            const item = this.items?.find((n) => n.id === data.data.id);
            if (item) item.read = true;
            return data.data.url;
        },
        async readAll() {
            const { data } = await api.post('/notifications/read-all');
            this.items?.forEach((n) => (n.read = true));
            this.setUnread(data.unread);
        },
        reset() {
            this.unread = 0;
            this.items = null;
        },
    },
});

/** Inside the app, or a full page load for the admin panel. */
export function goTo(router, url) {
    if (!url || url === '/') return router.push('/');
    if (url.startsWith('/admin')) {
        window.location.assign(url);
        return Promise.resolve();
    }
    return router.push(url).catch(() => router.push('/'));
}
