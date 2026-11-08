<template>
    <header class="flex items-center justify-between gap-3 px-4 pt-5 pb-3">
        <RouterLink to="/" class="flex items-baseline gap-2" aria-label="দাওয়াত — হোম">
            <span class="font-serif text-3xl leading-none font-bold text-leaf">দাওয়াত</span>
            <span v-if="!middleLink" class="hidden text-sm text-muted min-[360px]:inline">পাড়ার ঘরের খাবার</span>
        </RouterLink>

        <RouterLink v-if="middleLink === 'orders'" to="/orders" class="btn-quiet ml-auto min-h-10 w-auto whitespace-nowrap px-2">আমার খাবার</RouterLink>
        <RouterLink v-if="middleLink === 'shahidar'" to="/shahidar" class="btn-quiet ml-auto min-h-10 w-auto whitespace-nowrap px-2"><span class="max-[359px]:hidden">আজকের&nbsp;</span>তালিকা</RouterLink>
        <RouterLink v-if="!auth.isSignedIn && route.name !== 'login'" to="/login" class="btn-quiet -mr-4 min-h-10">
            ঢুকুন
        </RouterLink>
        <button v-else-if="auth.isSignedIn" type="button" class="btn-quiet -mr-4 min-h-10 w-auto whitespace-nowrap" @click="logout">
            বের হন
        </button>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const isShahidar = computed(() => auth.user?.roles?.includes('shahidar'));
// One link between the logo and "বের হন"; the tagline makes room for it.
const middleLink = computed(() => {
    if (!auth.isSignedIn) return null;
    if (isShahidar.value) return route.name === 'shahidar' ? null : 'shahidar';
    return ['orders', 'regular'].includes(route.name) ? null : 'orders';
});

async function logout() {
    await auth.logout();
    router.push('/');
}
</script>
