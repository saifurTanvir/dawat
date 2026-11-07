import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    {
        path: '/',
        name: 'feed',
        component: () => import('./pages/FeedPage.vue'),
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
