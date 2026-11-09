<template>
    <header class="flex items-center justify-between gap-2 px-4 pt-5 pb-3">
        <!-- The full logo always shows; on narrow phones with an extra link it gets a little smaller so nothing overlaps. -->
        <RouterLink to="/" class="flex min-w-0 shrink-0 items-center" :class="middleLink ? 'gap-2 max-[419px]:gap-1' : 'gap-2'" aria-label="দাওয়াত — হোম">
            <img src="/brand/mark.png" alt="" width="63" height="32" class="w-auto shrink-0 self-center" :class="middleLink ? 'h-8 max-[419px]:h-7 max-[339px]:h-5' : 'h-8'" />
            <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="w-auto shrink-0 self-center" :class="middleLink ? 'h-8 max-[419px]:h-7 max-[339px]:h-5' : 'h-8'" />
            <span v-if="!middleLink" class="hidden text-sm text-muted min-[440px]:inline">পাড়ার ঘরের খাবার</span>
        </RouterLink>

        <RouterLink v-if="middleLink === 'orders'" to="/orders" class="btn-quiet ml-auto min-h-10 w-auto shrink-0 whitespace-nowrap px-2">আমার খাবার</RouterLink>
        <RouterLink v-if="middleLink === 'shahidar'" to="/shahidar" class="btn-quiet ml-auto min-h-10 w-auto shrink-0 whitespace-nowrap px-2"><span class="max-[419px]:hidden">আজকের&nbsp;</span>তালিকা</RouterLink>
        <RouterLink v-if="!auth.isSignedIn && route.name !== 'login'" to="/login" class="btn-quiet -mr-4 min-h-10">
            ঢুকুন
        </RouterLink>
        <button v-else-if="auth.isSignedIn" type="button" class="btn-quiet -mr-4 min-h-10 w-auto shrink-0 whitespace-nowrap" @click="logout">
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
