<template>
    <!-- F66: a tapped phone notification lands here: marked read, then off to its page -->
    <p class="pt-6 text-muted">খোলা হচ্ছে…</p>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { goTo, useNotificationsStore } from '@/stores/notifications';

const route = useRoute();
const router = useRouter();
const store = useNotificationsStore();

onMounted(async () => {
    let url = '/notifications';
    try {
        url = await store.open(route.params.id);
    } catch {
        // someone else's or old: show the list instead
    }
    if (url.startsWith('/admin')) window.location.replace(url);
    else router.replace(url).catch(() => router.replace('/'));
});
</script>
