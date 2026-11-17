<template>
    <!-- F66: notifications in the app itself; the number is what is not read yet -->
    <RouterLink
        to="/notifications"
        class="relative -mr-1 flex size-10 shrink-0 items-center justify-center rounded-full text-ink hover:bg-plate"
        :aria-label="store.unread ? `নোটিফিকেশন, ${bn(store.unread)}টা নতুন` : 'নোটিফিকেশন'"
    >
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 9a6 6 0 1 1 12 0c0 4.5 1.5 6.5 2.5 7.5h-17C4.5 15.5 6 13.5 6 9Z" />
            <path d="M10 19.5a2 2 0 0 0 4 0" />
        </svg>
        <span
            v-if="store.unread"
            class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-chili px-1 text-xs font-bold leading-none text-white"
            aria-hidden="true"
        >{{ store.unread > 99 ? '৯৯+' : bn(store.unread) }}</span>
    </RouterLink>
</template>

<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { bn } from '@/lib/bn';
import { useNotificationsStore } from '@/stores/notifications';

const store = useNotificationsStore();
const route = useRoute();
let timer = null;

function refreshIfVisible() {
    if (document.visibilityState === 'visible') store.refreshCount();
}

onMounted(() => {
    store.refreshCount();
    timer = setInterval(refreshIfVisible, 60_000);
    document.addEventListener('visibilitychange', refreshIfVisible);
    navigator.serviceWorker?.addEventListener?.('message', refreshIfVisible);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', refreshIfVisible);
});
// Moving around the app often means something was dealt with.
watch(() => route.path, () => store.refreshCount());
</script>
