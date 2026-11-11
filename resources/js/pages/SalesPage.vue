<template>
    <!-- F47: what the cook sold, what she earns, and what customers said (no names). -->
    <section class="pt-4" aria-labelledby="sales-title">
        <RouterLink :to="{ name: 'cook' }" class="text-sm text-leaf">← আমার রান্নাঘর</RouterLink>
        <h1 id="sales-title" class="mt-2 font-serif text-2xl font-bold">যা বিক্রি করেছি</h1>
        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error" class="error-text mt-6" role="alert">{{ error }}</p>

        <template v-else-if="data">
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-[var(--radius-plate)] bg-plate p-4">
                    <p class="text-sm text-muted">আপনার পাওনা (বাকি)</p>
                    <p class="text-2xl font-bold text-leaf">{{ taka(data.totals.due_paisa) }}</p>
                    <p class="text-xs text-muted">পরিশোধ হয়েছে {{ taka(data.totals.settled_paisa) }}</p>
                </div>
                <div class="rounded-[var(--radius-plate)] bg-plate p-4">
                    <p class="text-sm text-muted">রেটিং</p>
                    <p class="text-2xl font-bold"><span class="text-turmeric">★</span> {{ data.rating_avg ? bn(data.rating_avg.toFixed(1)) : '—' }}</p>
                    <p class="text-xs text-muted">{{ bn(data.rating_count) }}টা রিভিউ</p>
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">গত {{ bn(14) }} দিন · {{ bn(data.totals.plates) }} প্লেট<template v-if="data.totals.commission_percent"> · দাওয়াতের {{ bn(data.totals.commission_percent) }}% বাদে</template></p>

            <h2 class="mt-6 text-lg font-semibold">দিন আর বেলা</h2>
            <p v-if="!data.days.length" class="mt-1 text-muted">এই সময়ে কিছু বিক্রি হয়নি।</p>
            <div v-for="d in data.days" :key="`${d.date}-${d.meal_slot}`" class="mt-3 rounded-[var(--radius-plate)] bg-plate p-4 text-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold">{{ dateText(d.date) }} · {{ d.meal_slot_label }}</p>
                    <p class="shrink-0 font-semibold">{{ taka(d.earned_paisa) }}</p>
                </div>
                <p class="text-muted">{{ d.titles.join(', ') }}</p>
                <p class="mt-1">নিয়মিত {{ bn(d.regular_plates) }} প্লেট · ফিড {{ bn(d.feed_plates) }} প্লেট</p>
                <p class="text-muted">
                    পৌঁছেছে {{ bn(d.delivered) }}টা<template v-if="d.failed"> · পৌঁছানো যায়নি {{ bn(d.failed) }}টা</template><template v-if="d.cancelled"> · বাতিল {{ bn(d.cancelled) }}টা</template><template v-if="d.open"> · চলছে {{ bn(d.open) }}টা</template>
                </p>
                <p class="mt-1 text-xs" :class="d.settled ? 'text-leaf' : 'text-muted'">
                    {{ d.settled ? '✓ দাওয়াত পরিশোধ করেছে' : d.earned_paisa ? 'দাওয়াত পরে পরিশোধ করবে' : '' }}<template v-if="d.cash_to_cook_paisa"> · নিজে হাতে পেয়েছেন {{ taka(d.cash_to_cook_paisa) }}</template>
                </p>
            </div>

            <h2 class="mt-6 text-lg font-semibold">গ্রাহকরা যা বললেন</h2>
            <p v-if="notice" class="mt-2 rounded-xl bg-leaf-soft p-3 text-sm" role="status">{{ notice }}</p>
            <div class="mt-2 rounded-[var(--radius-plate)] bg-plate p-4">
                <ReviewList :reviews="data.reviews" :reply-path="(r) => `/my/reviews/${r.id}/reply`" @replied="onReplied" />
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import ReviewList from '@/components/ReviewList.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const data = ref(null);
const loading = ref(true);
const error = ref('');
const notice = ref('');

function dateText(d) {
    return new Intl.DateTimeFormat('bn-BD', { weekday: 'short', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${d}T00:00:00Z`));
}

async function load() {
    try {
        data.value = (await api.get('/my/sales')).data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function onReplied(message) {
    notice.value = message;
    await load();
}

onMounted(load);
</script>
