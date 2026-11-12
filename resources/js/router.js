import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    {
        path: '/',
        name: 'feed',
        component: () => import('./pages/FeedPage.vue'),
    },
    {
        // F36: install the PWA; this link is what gets sent on WhatsApp.
        path: '/app',
        name: 'install',
        component: () => import('./pages/InstallPage.vue'),
    },
    {
        path: '/q/:code',
        name: 'qr',
        component: () => import('./pages/QrPage.vue'),
    },
    {
        path: '/p/:id(\\d+)',
        name: 'post',
        component: () => import('./pages/PostPage.vue'),
    },
    {
        // F53: apply to work as a shahidar (public)
        path: '/join/shahidar',
        name: 'shahidar-apply',
        component: () => import('./pages/ShahidarApplyPage.vue'),
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/LoginPage.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/cook',
        name: 'cook',
        component: () => import('./pages/CookPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/cook/setup',
        name: 'cook-setup',
        component: () => import('./pages/KitchenSetupPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/cook/new',
        name: 'new-post',
        component: () => import('./pages/NewPostPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        // F47
        path: '/cook/sales',
        name: 'sales',
        component: () => import('./pages/SalesPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/history',
        name: 'history',
        component: () => import('./pages/HistoryPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/cook/posts',
        name: 'my-posts',
        component: () => import('./pages/MyPostsPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/regular',
        name: 'regular',
        component: () => import('./pages/RegularPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/orders',
        name: 'orders',
        component: () => import('./pages/OrdersPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/shahidar',
        name: 'shahidar',
        component: () => import('./pages/ShahidarPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        // F46: a shahidar acts for one cook
        path: '/shahidar/kitchens/:id',
        name: 'shahidar-kitchen',
        component: () => import('./pages/ShahidarKitchenPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        // F51: open the app as someone else
        path: '/as/:code',
        name: 'view-as',
        component: () => import('./pages/ViewAsPage.vue'),
    },
    {
        // F50: one-time login link
        path: '/l/:token',
        name: 'link-login',
        component: () => import('./pages/LinkLoginPage.vue'),
    },
    {
        path: '/s/:code',
        name: 'sticker',
        component: () => import('./pages/StickerPage.vue'),
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./pages/NotFoundPage.vue'),
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.load();

    if (to.meta.requiresAuth && !auth.isSignedIn) {
        return { name: 'login', query: { next: to.fullPath } };
    }
    if (to.meta.guestOnly && auth.isSignedIn) {
        return { name: 'feed' };
    }
    return true;
});
