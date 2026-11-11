// F48: which role the app is being used as — গ্রাহক, গৃহিণী, শাহিদার or অ্যাডমিন —
// and the pages of each. Someone with several roles switches between them.
import { ref } from 'vue';

const KEY = 'dawat.mode';

export const MODES = {
    customer: {
        label: 'গ্রাহক',
        icon: '🍽️',
        home: '/',
        links: [
            { to: '/', label: 'আশেপাশের খাবার' },
            { to: '/orders', label: 'আমার খাবার' },
            { to: '/regular', label: 'প্রতিদিন ঘরের খাবার' },
            { to: '/history', label: 'যা পেয়েছি · রিভিউ' },
        ],
    },
    cook: {
        label: 'গৃহিণী',
        icon: '🍳',
        home: '/cook',
        links: [
            { to: '/cook', label: 'আমার রান্নাঘর' },
            { to: '/cook/new', label: 'আজকের রান্না পোস্ট' },
            { to: '/cook/posts', label: 'আমার পোস্ট' },
            { to: '/cook/sales', label: 'যা বিক্রি করেছি' },
        ],
    },
    shahidar: {
        label: 'শাহিদার',
        icon: '🛵',
        home: '/shahidar',
        links: [{ to: '/shahidar', label: 'আজকের তালিকা' }],
    },
    admin: {
        label: 'অ্যাডমিন',
        icon: '🛠️',
        home: '/admin',
        external: true,
        links: [{ to: '/admin', label: 'অ্যাডমিন প্যানেল', external: true }],
    },
};

/** Roles this user can view the app as, in a fixed order. */
export function availableModes(user) {
    if (!user) return [];
    const roles = user.roles ?? [];
    const out = ['customer'];
    if (roles.includes('cook') || user.kitchen) out.push('cook');
    if (roles.includes('shahidar')) out.push('shahidar');
    if (roles.includes('admin')) out.push('admin');
    return out;
}

/** The role a page belongs to, or null for shared pages (feed, a post, the install page…). */
export function modeOfRoute(path) {
    if (path.startsWith('/cook')) return 'cook';
    if (path.startsWith('/shahidar')) return 'shahidar';
    if (['/orders', '/regular', '/history'].some((p) => path.startsWith(p))) return 'customer';
    return null;
}

function read() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

export const chosenMode = ref(read());

export function rememberMode(mode) {
    chosenMode.value = mode;
    try {
        localStorage.setItem(KEY, mode);
    } catch {
        /* the choice just won't survive a reload */
    }
}

/** What to show now: the page's own role, else the last chosen one, else the most specific role. */
export function currentMode(user, path) {
    const modes = availableModes(user);
    const fromRoute = modeOfRoute(path);
    if (fromRoute && modes.includes(fromRoute)) return fromRoute;
    if (chosenMode.value && modes.includes(chosenMode.value)) return chosenMode.value;
    return modes.find((m) => m === 'shahidar') ?? modes.find((m) => m === 'cook') ?? 'customer';
}
