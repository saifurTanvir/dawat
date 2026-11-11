<template>
    <!-- F50: a one-time login link from the Dawat team, for when the SMS did not arrive. -->
    <section class="pt-10 text-center">
        <p v-if="busy" class="text-muted">ঢোকানো হচ্ছে…</p>
        <template v-else-if="error">
            <p class="error-text" role="alert">{{ error }}</p>
            <RouterLink :to="{ name: 'login' }" class="btn-primary mt-6">মোবাইল নম্বর দিয়ে ঢুকুন</RouterLink>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { errorMessage } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const busy = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        const user = await auth.loginWithLink(String(route.params.token));
        const roles = user.roles ?? [];
        router.replace(roles.includes('shahidar') ? '/shahidar' : user.kitchen ? '/cook' : roles.includes('buyer') ? '/' : '/');
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
});
</script>
