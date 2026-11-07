<template>
    <section class="pt-4" aria-labelledby="my-posts-title">
        <RouterLink to="/cook" class="text-sm text-leaf">← আমার রান্নাঘর</RouterLink>
        <div class="mt-2 flex items-center justify-between gap-3">
            <h1 id="my-posts-title" class="font-serif text-2xl font-bold">আমার পোস্ট</h1>
            <RouterLink :to="{ name: 'new-post' }" class="btn-quiet -mr-4 min-h-10">নতুন পোস্ট</RouterLink>
        </div>

        <p v-if="route.query.posted" class="mt-4 rounded-xl bg-leaf-soft p-4 font-semibold text-leaf-deep" role="status">
            পোস্ট হয়ে গেছে। আশেপাশের মানুষ এখন দেখতে পাবেন।
        </p>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error" class="error-text mt-6" role="alert">{{ error }}</p>

        <div v-else-if="!posts.length" class="mt-6 rounded-[var(--radius-plate)] border border-dashed border-rule bg-plate p-6">
            <p class="font-semibold">গত ৭ দিনে কোনো পোস্ট নেই।</p>
            <RouterLink :to="{ name: 'new-post' }" class="btn-primary mt-4">আজকের রান্না পোস্ট করুন</RouterLink>
        </div>

        <ul v-else class="mt-6 space-y-4">
            <li v-for="post in posts" :key="post.id" class="rounded-[var(--radius-plate)] bg-plate p-4">
                <div class="flex gap-3">
                    <img
                        v-if="post.images?.[0]"
                        :src="post.images[0].url"
                        alt=""
                        class="size-20 shrink-0 rounded-xl object-cover"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold leading-snug">{{ post.title }}</p>
                        <p class="mt-0.5 text-sm text-muted">
                            {{ taka(post.price_paisa) }} · {{ post.meal_slot_label }} · {{ agoText(post.published_at) }}
                        </p>
                        <p class="mt-1 text-sm">
                            <span class="rounded-full px-2 py-0.5" :class="statusClass(post)">{{ statusText(post) }}</span>
                            <span v-if="post.contact_counts" class="ml-2 text-muted">{{ bn(post.contact_counts) }} বার যোগাযোগ</span>
                        </p>
                    </div>
                </div>

                <div v-if="isOpen(post)" class="mt-4 flex items-center gap-3">
                    <div class="flex items-center rounded-xl border border-rule" role="group" :aria-label="`${post.title}: কয় প্লেট বাকি`">
                        <button
                            type="button"
                            class="size-11 text-2xl text-leaf disabled:text-rule"
                            :disabled="busyId === post.id || post.plates_left <= 0"
                            aria-label="এক প্লেট কম"
                            @click="setPlates(post, post.plates_left - 1)"
                        >−</button>
                        <output class="min-w-16 text-center" aria-live="polite">{{ bn(post.plates_left) }}/{{ bn(post.plates_total) }}</output>
                        <button
                            type="button"
                            class="size-11 text-2xl text-leaf disabled:text-rule"
                            :disabled="busyId === post.id || post.plates_left >= post.plates_total"
                            aria-label="এক প্লেট বেশি"
                            @click="setPlates(post, post.plates_left + 1)"
                        >+</button>
                    </div>
                    <button
                        v-if="post.status === 'live'"
                        type="button"
                        class="btn-quiet flex-1 border border-rule"
                        :disabled="busyId === post.id"
                        @click="soldOut(post)"
                    >শেষ হয়ে গেছে</button>
                </div>
                <p v-if="rowError[post.id]" class="error-text" role="alert">{{ rowError[post.id] }}</p>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { agoText, clockText, taka } from '@/lib/format';

const route = useRoute();
const posts = ref([]);
const loading = ref(true);
const error = ref('');
const busyId = ref(null);
const rowError = reactive({});

function isOpen(post) {
    return ['live', 'sold_out'].includes(post.status) && new Date(post.available_until) > new Date();
}

function statusText(post) {
    if (post.status === 'live' && new Date(post.available_until) <= new Date()) return 'সময় শেষ';
    if (post.status === 'live') return `চালু · ${clockText(post.available_until)} পর্যন্ত`;
    return post.status_label;
}

function statusClass(post) {
    if (post.status === 'live' && isOpen(post)) return 'bg-leaf-soft text-leaf-deep';
    if (post.status === 'hidden') return 'bg-chili/10 text-chili';
    return 'bg-steel text-muted';
}

function replace(updated) {
    const i = posts.value.findIndex((p) => p.id === updated.id);
    if (i >= 0) posts.value[i] = { ...posts.value[i], ...updated };
}

async function run(post, request) {
    busyId.value = post.id;
    rowError[post.id] = '';
    try {
        const { data } = await request();
        replace(data.data);
    } catch (e) {
        rowError[post.id] = errorMessage(e);
    } finally {
        busyId.value = null;
    }
}

function setPlates(post, platesLeft) {
    return run(post, () => api.patch(`/my/posts/${post.id}`, { plates_left: platesLeft }));
}

function soldOut(post) {
    return run(post, () => api.post(`/my/posts/${post.id}/sold-out`));
}

onMounted(async () => {
    try {
        const { data } = await api.get('/my/posts');
        posts.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});
</script>
