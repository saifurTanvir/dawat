<template>
    <header class="flex items-center justify-between gap-2 px-4 pt-5 pb-3">
        <!-- The full logo always shows; when signed in it gets a little smaller on narrow phones so the role button fits. -->
        <RouterLink to="/" class="flex min-w-0 shrink-0 items-center" :class="auth.isSignedIn ? 'gap-2 max-[419px]:gap-1' : 'gap-2'" aria-label="দাওয়াত — হোম">
            <img src="/brand/mark.png" alt="" width="63" height="32" class="w-auto shrink-0 self-center" :class="auth.isSignedIn ? 'h-8 max-[419px]:h-7 max-[359px]:h-5' : 'h-8'" />
            <img src="/brand/wordmark.png" alt="দাওয়াত" width="100" height="32" class="w-auto shrink-0 self-center" :class="auth.isSignedIn ? 'h-8 max-[419px]:h-7 max-[359px]:h-5' : 'h-8'" />
            <span v-if="!auth.isSignedIn" class="hidden text-sm text-muted min-[440px]:inline">পাড়ার ঘরের খাবার</span>
        </RouterLink>

        <!-- F48: which role the app is used as, and switching -->
        <ModeMenu v-if="auth.isSignedIn" />
        <RouterLink v-if="!auth.isSignedIn && route.name !== 'login'" to="/login" class="btn-quiet -mr-4 min-h-10">
            ঢুকুন
        </RouterLink>
        <button v-else-if="auth.isSignedIn" type="button" class="btn-quiet -mr-4 min-h-10 w-auto shrink-0 whitespace-nowrap px-3" @click="logout">
            বের হন
        </button>
    </header>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router';
import ModeMenu from '@/components/ModeMenu.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push('/');
}
</script>
