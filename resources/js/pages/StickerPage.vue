<template>
    <section class="pt-4" aria-labelledby="st-title">
        <h1 id="st-title" class="font-serif text-2xl font-bold">দাওয়াতের বক্স</h1>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>

        <div v-else-if="info?.order" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-5">
            <p class="text-sm text-muted">বক্স {{ info.order.code }} · {{ info.order.meal_slot_label }}</p>
            <p class="mt-1 text-lg font-semibold">{{ info.order.title || info.order.kitchen }}</p>
            <p class="text-muted">{{ info.order.kitchen }} · {{ bn(info.order.plates) }} প্লেট · {{ taka(info.order.total_paisa) }}</p>
            <p class="mt-3 font-semibold">{{ done ? 'পৌঁছেছে' : info.order.status_label }}</p>

            <button v-if="info.order.can_confirm && !done" type="button" class="btn-primary mt-4" :disabled="busy" @click="confirm">খাবার পেয়েছি</button>
            <p v-if="message" class="mt-3 rounded-lg bg-leaf-soft p-3" role="status">{{ message }}</p>
            <RouterLink v-if="info.state === 'shahidar'" to="/shahidar" class="btn-quiet -ml-4 mt-2">আজকের তালিকায় যান</RouterLink>
        </div>

        <div v-else class="mt-4 rounded-[var(--radius-plate)] bg-plate p-5">
            <p>{{ info?.state === 'blank' ? 'এই স্টিকারটা এখনো কোনো বক্সে লাগানো হয়নি।' : 'এটা দাওয়াতের একজন নিয়মিত গ্রাহকের খাবারের বক্স।' }}</p>
            <p v-if="!auth.isSignedIn" class="mt-3 text-muted">বক্সটা আপনার হলে নিজের নম্বর দিয়ে ঢুকে আবার স্ক্যান করুন।</p>
            <RouterLink v-if="!auth.isSignedIn" :to="{ name: 'login', query: { next: route.fullPath } }" class="btn-primary mt-4">ঢুকুন</RouterLink>
            <RouterLink v-else to="/" class="btn-quiet -ml-4 mt-2">আজকের খাবার দেখুন</RouterLink>
        </div>
        <p v-if="error" class="error-text mt-4" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();
const loading = ref(true);
const busy = ref(false);
const info = ref(null);
const error = ref('');
const message = ref('');
const done = ref(false);

onMounted(async () => {
    try {
        const { data } = await api.get(`/stickers/${encodeURIComponent(route.params.code)}`);
        info.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

async function confirm() {
    busy.value = true;
    try {
        const { data } = await api.post(`/stickers/${encodeURIComponent(route.params.code)}/received`);
        message.value = data.message;
        done.value = true;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
