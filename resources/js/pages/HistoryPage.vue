<template>
    <!-- F47: every box this person got, its money, and their review. -->
    <section class="pt-4" aria-labelledby="history-title">
        <h1 id="history-title" class="font-serif text-2xl font-bold">যা পেয়েছি</h1>
        <p class="text-sm text-muted">গত ২ মাসের সব বক্স। খাবার পৌঁছানোর ৩ দিনের মধ্যে তারা দিতে পারবেন।</p>
        <p v-if="notice" class="mt-3 rounded-xl bg-leaf-soft p-3 text-sm" role="status">{{ notice }}</p>
        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error" class="error-text mt-6" role="alert">{{ error }}</p>
        <p v-else-if="!boxes.length" class="mt-6 text-muted">এখনো কোনো বক্স নেই।</p>

        <article v-for="o in boxes" :key="o.id" class="mt-3 rounded-[var(--radius-plate)] bg-plate p-4 text-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="font-semibold">{{ dateText(o.date) }} · {{ o.meal_slot_label }}</p>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="o.status === 'delivered' ? 'bg-leaf-soft text-leaf-deep' : ['cancelled', 'failed'].includes(o.status) ? 'bg-steel' : 'bg-turmeric-soft'">{{ o.status_label }}</span>
            </div>
            <p class="mt-0.5 text-base">{{ o.title || 'খাবার' }}</p>
            <p class="text-muted">{{ o.kitchen }} · {{ o.kind }} · বক্স {{ o.code }} · {{ bn(o.plates) }} প্লেট · {{ taka(o.total_paisa) }}</p>
            <p v-if="o.reason" class="mt-1 text-muted">কারণ: {{ o.reason }}</p>
            <p v-if="o.delivered_at" class="mt-1">পৌঁছেছে {{ clockText(o.delivered_at) }}<template v-if="o.delivered_by"> · {{ o.delivered_by }}</template></p>
            <p v-if="o.payment" class="mt-0.5" :class="o.payment === 'বাকি' ? 'font-semibold text-chili' : 'text-muted'">টাকা: {{ o.payment }}</p>

            <!-- Review -->
            <div v-if="o.review && reviewing !== o.id" class="mt-2 rounded-lg bg-leaf-soft/60 px-3 py-2">
                <p><span class="text-turmeric">{{ '★'.repeat(o.review.stars) }}</span><span class="text-rule">{{ '★'.repeat(5 - o.review.stars) }}</span> আপনার রিভিউ</p>
                <p v-if="o.review.body">“{{ o.review.body }}”</p>
                <p v-if="o.review.reply" class="mt-1"><span class="font-semibold">{{ o.review.reply_label }}:</span> {{ o.review.reply }}</p>
                <button v-if="o.can_review" type="button" class="mt-1 text-xs font-semibold text-leaf" @click="startReview(o)">বদলান</button>
            </div>
            <form v-if="reviewing === o.id" class="mt-2 space-y-2" @submit.prevent="sendReview(o)">
                <p class="font-semibold">খাবার কেমন লাগল?</p>
                <StarPicker v-model="form.stars" />
                <textarea v-model="form.body" class="field text-base" rows="2" maxlength="500" placeholder="কিছু বলবেন? (ইচ্ছা হলে) — শুধু গৃহিণী আর দাওয়াত টিম দেখবেন"></textarea>
                <p v-if="formError" class="error-text" role="alert">{{ formError }}</p>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary w-auto px-5" :disabled="busy || !form.stars">পাঠান</button>
                    <button type="button" class="btn-quiet w-auto" @click="reviewing = null">থাক</button>
                </div>
            </form>
            <button v-else-if="o.can_review && !o.review" type="button" class="btn-primary mt-2 w-auto px-4" @click="startReview(o)">★ রিভিউ দিন</button>
        </article>

        <button v-if="hasMore" type="button" class="btn-quiet mt-4 w-full border border-rule" :disabled="loading" @click="more">আরও দেখুন</button>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import StarPicker from '@/components/StarPicker.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { clockText, taka } from '@/lib/format';

const boxes = ref([]);
const page = ref(1);
const hasMore = ref(false);
const loading = ref(true);
const error = ref('');
const notice = ref('');
const busy = ref(false);
const reviewing = ref(null);
const form = reactive({ stars: 0, body: '' });
const formError = ref('');

function dateText(d) {
    return new Intl.DateTimeFormat('bn-BD', { weekday: 'short', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${d}T00:00:00Z`));
}

async function load(p = 1) {
    loading.value = true;
    try {
        const { data } = await api.get('/my/history', { params: { page: p } });
        boxes.value = p === 1 ? data.data : [...boxes.value, ...data.data];
        hasMore.value = data.meta.has_more;
        page.value = p;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}
const more = () => load(page.value + 1);

function startReview(o) {
    reviewing.value = o.id;
    form.stars = o.review?.stars ?? 0;
    form.body = o.review?.body ?? '';
    formError.value = '';
}

async function sendReview(o) {
    busy.value = true;
    formError.value = '';
    try {
        const { data } = await api.post(`/my/history/${o.id}/review`, { stars: form.stars, body: form.body });
        const i = boxes.value.findIndex((b) => b.id === o.id);
        boxes.value[i] = data.data;
        notice.value = data.message;
        reviewing.value = null;
    } catch (e) {
        formError.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

onMounted(() => load());
</script>
