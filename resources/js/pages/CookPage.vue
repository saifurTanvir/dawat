<template>
    <section class="pt-4" aria-labelledby="cook-title">
        <h1 id="cook-title" class="font-serif text-2xl font-bold">আমার রান্নাঘর</h1>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>

        <!-- Not registered yet -->
        <form v-else-if="!kitchen" class="mt-4" novalidate @submit.prevent="register">
            <p class="text-muted">
                পরিবারের জন্য রান্না থেকে এক-দুই প্লেট আলাদা করে পাড়ার মানুষের কাছে বিক্রি করুন।
                দাওয়াত টিম আপনার সাথে কথা বলে অনুমোদন দেবে।
            </p>
            <label for="display_name" class="mt-6 mb-2 block font-semibold">রান্নাঘরের নাম</label>
            <input
                id="display_name"
                v-model="displayName"
                class="field"
                type="text"
                maxlength="60"
                placeholder="যেমন: রহিমা খালার রান্নাঘর"
                :aria-invalid="Boolean(error)"
                aria-describedby="name-help name-error"
            />
            <p id="name-help" class="mt-2 text-sm text-muted">ক্রেতারা এই নামটা দেখবেন। নিজের নাম না দিতে চাইলে ডাকনাম দিন।</p>
            <p v-if="error" id="name-error" class="error-text" role="alert">{{ error }}</p>
            <button type="submit" class="btn-primary mt-6" :disabled="busy">
                {{ busy ? 'নিবন্ধন হচ্ছে…' : 'দাওয়াতে যোগ দিন' }}
            </button>
        </form>

        <!-- Registered: show where they stand -->
        <div v-else class="mt-4">
            <p v-if="route.query.posted" class="mb-4 rounded-xl bg-leaf-soft p-4 font-semibold text-leaf-deep" role="status">
                পোস্ট হয়ে গেছে। আশেপাশের মানুষ এখন দেখতে পাবেন।
            </p>
            <RouterLink v-if="kitchen.can_post" :to="{ name: 'new-post' }" class="btn-primary mb-6">
                আজকের রান্না পোস্ট করুন
            </RouterLink>
            <div class="rounded-[var(--radius-plate)] bg-plate p-5" :class="statusTone.border">
                <p class="text-sm text-muted">{{ kitchen.display_name }}</p>
                <p class="mt-1 text-lg font-semibold" :class="statusTone.text">{{ kitchen.status_label }}</p>
                <p class="mt-2 text-muted">{{ statusMessage }}</p>
                <p v-if="kitchen.rejection_reason" class="mt-3 rounded-lg bg-steel p-3 text-sm">
                    কারণ: {{ kitchen.rejection_reason }}
                </p>
            </div>

            <div v-if="!kitchen.is_set_up" class="mt-6 rounded-[var(--radius-plate)] bg-turmeric-soft p-5">
                <p class="font-semibold">সেটআপ বাকি আছে</p>
                <p class="mt-1 text-muted">বাসার লোকেশন আর এলাকা দিন। এটা ছাড়া খাবার পোস্ট করা যাবে না।</p>
                <RouterLink :to="{ name: 'cook-setup' }" class="btn-primary mt-4">সেটআপ করুন</RouterLink>
            </div>
            <RouterLink v-else :to="{ name: 'cook-setup' }" class="btn-quiet -ml-4 mt-4">
                লোকেশন ও এলাকা বদলান
            </RouterLink>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const kitchen = ref(null);
const loading = ref(true);
const displayName = ref('');
const error = ref('');
const busy = ref(false);

const messages = {
    pending: 'দাওয়াত টিম শীঘ্রই আপনার সাথে ফোনে যোগাযোগ করবে। অনুমোদনের পর রান্না পোস্ট করতে পারবেন।',
    approved: 'অনুমোদন হয়ে গেছে। রান্না হলেই ছবি তুলে পোস্ট দিন।',
    rejected: 'এখন অনুমোদন দেওয়া যায়নি। প্রশ্ন থাকলে দাওয়াত টিমের সাথে যোগাযোগ করুন।',
    suspended: 'আপনার রান্নাঘর সাময়িক বন্ধ আছে। দাওয়াত টিমের সাথে যোগাযোগ করুন।',
};

const statusMessage = computed(() => messages[kitchen.value?.status] ?? '');
const statusTone = computed(() => {
    switch (kitchen.value?.status) {
        case 'approved':
            return { border: 'border-2 border-leaf', text: 'text-leaf' };
        case 'pending':
            return { border: 'border-2 border-turmeric', text: 'text-ink' };
        default:
            return { border: 'border-2 border-chili', text: 'text-chili' };
    }
});

onMounted(async () => {
    try {
        const { data } = await api.get('/kitchen');
        kitchen.value = data.data;
    } catch (e) {
        if (e.response?.status !== 404) {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
});

async function register() {
    error.value = '';
    busy.value = true;
    try {
        const { data } = await api.post('/kitchen', { display_name: displayName.value });
        kitchen.value = data.data;
        auth.loaded = false;
        await auth.load();
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
