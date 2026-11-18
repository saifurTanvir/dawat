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
            <RouterLink v-if="done" :to="{ name: 'history' }" class="btn-primary mt-3">★ খাবার কেমন লাগল? রিভিউ দিন</RouterLink>
            <RouterLink v-if="info.state === 'shahidar'" to="/shahidar" class="btn-quiet -ml-4 mt-2">আজকের তালিকায় যান</RouterLink>
        </div>

        <!-- F68: an office drop's bag: its contact person takes the whole drop -->
        <div v-else-if="info?.state === 'office_contact'" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-5">
            <p class="text-sm text-muted">অফিস ড্রপ · {{ info.drop.meal_slot_label }}</p>
            <p class="mt-1 text-lg font-semibold">{{ info.drop.company }}</p>
            <p class="text-muted">{{ bn(info.drop.boxes) }}টা বক্স · {{ bn(info.drop.plates) }} প্লেট · পৌঁছেছে {{ bn(info.drop.delivered) }}টা</p>
            <button v-if="info.drop.can_confirm && !done" type="button" class="btn-primary mt-4" :disabled="busy" @click="confirm">{{ bn(info.drop.picked) }}টা বক্স বুঝে পেয়েছি</button>
            <p v-if="message" class="mt-3 rounded-lg bg-leaf-soft p-3" role="status">{{ message }}</p>
        </div>
        <div v-else-if="info?.state === 'office'" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-5">
            <p>এটা দাওয়াতের একটা অফিসের খাবারের ব্যাগ।</p>
            <p v-if="!auth.isSignedIn" class="mt-3 text-muted">আপনি অফিসের দায়িত্বপ্রাপ্ত লোক হলে নিজের নম্বর দিয়ে ঢুকে আবার স্ক্যান করুন।</p>
            <RouterLink v-if="!auth.isSignedIn" :to="{ name: 'login', query: { next: route.fullPath } }" class="btn-primary mt-4">ঢুকুন</RouterLink>
        </div>
        <!-- F67: whoever holds the box confirms with the SMS code; nothing about the box is shown first -->
        <div v-else-if="info?.can_confirm_with_code" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-5">
            <template v-if="!done">
                <p class="text-lg font-semibold">খাবার হাতে পেয়েছেন?</p>
                <p class="mt-1 text-muted">বক্স বুক হওয়ার সময় SMS-এ যে ৪ অঙ্কের ডেলিভারি কোড এসেছে, সেটা লিখুন।</p>
                <form class="mt-3 flex gap-2" @submit.prevent="confirmWithCode">
                    <label for="dc" class="sr-only">ডেলিভারি কোড</label>
                    <input id="dc" v-model.trim="code" class="field flex-1 text-center text-xl tracking-widest" inputmode="numeric" maxlength="4" autocomplete="one-time-code" placeholder="০০০০" />
                    <button type="submit" class="btn-primary w-auto px-5" :disabled="busy || !/^[0-9০-৯]{4}$/.test(code)">খাবার পেয়েছি</button>
                </form>
            </template>
            <p v-if="message" class="mt-3 rounded-lg bg-leaf-soft p-3" role="status">{{ message }}</p>
            <template v-if="!auth.isSignedIn">
                <p class="mt-4 text-sm text-muted">দাওয়াতে নিজের নম্বরে ঢোকা থাকলে কোড ছাড়াই স্ক্যান করে নিশ্চিত করা যায়।</p>
                <RouterLink :to="{ name: 'login', query: { next: route.fullPath } }" class="btn-quiet -ml-4 mt-1">নিজের নম্বর দিয়ে ঢুকুন</RouterLink>
            </template>
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
const code = ref('');

/** Bangla digits typed on a Bangla keyboard become 0–9. */
function asciiDigits(v) {
    return v.replace(/[০-৯]/g, (d) => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));
}

async function confirmWithCode() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/stickers/${encodeURIComponent(route.params.code)}/confirm`, { code: asciiDigits(code.value) });
        message.value = data.message;
        done.value = true;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

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
