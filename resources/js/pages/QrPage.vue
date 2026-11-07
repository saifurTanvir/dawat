<template>
    <section class="pt-10" aria-live="polite">
        <template v-if="!error">
            <p class="font-serif text-2xl font-bold">আজকের রান্না খোঁজা হচ্ছে…</p>
        </template>
        <template v-else>
            <h1 class="font-serif text-2xl font-bold">QR কোডটি খোলা যায়নি</h1>
            <p class="mt-2 text-muted">{{ error }}</p>
            <RouterLink to="/" class="btn-primary mt-6">আশেপাশের খাবার দেখুন</RouterLink>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, errorMessage } from '@/lib/api';
import { useFeedStore } from '@/stores/feed';

const route = useRoute();
const router = useRouter();
const feed = useFeedStore();
const error = ref('');

onMounted(async () => {
    try {
        const { data } = await api.get(`/qr/${encodeURIComponent(route.params.code)}`);
        // Remember which printed code brought them, so a call or message can be credited to it (F18).
        feed.rememberQr({ code: data.data.code, area_slug: data.data.area.slug, label: data.data.label });
        router.replace({ name: 'feed' });
    } catch (e) {
        error.value = errorMessage(e);
    }
});
</script>
