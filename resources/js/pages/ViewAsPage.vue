<template>
    <!-- F51: opening the app as someone else, from the admin panel or a shahidar. -->
    <section class="pt-10 text-center">
        <p v-if="busy" class="text-muted">অ্যাপ খোলা হচ্ছে…</p>
        <template v-else-if="error">
            <p class="error-text" role="alert">{{ error }}</p>
            <RouterLink to="/" class="btn-primary mt-6">হোমে যান</RouterLink>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { errorMessage } from '@/lib/api';
import { rememberMode } from '@/lib/mode';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const busy = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        const user = await auth.startImpersonation(String(route.params.code));
        const roles = user.roles ?? [];
        const to = user.kitchen ? '/cook' : roles.includes('shahidar') ? '/shahidar' : '/regular';
        rememberMode(to === '/cook' ? 'cook' : to === '/shahidar' ? 'shahidar' : 'customer');
        router.replace(to);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
});
</script>
