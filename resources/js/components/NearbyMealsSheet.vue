<template>
    <!-- F85: other cooks' food near the regular customer today, with filters; pick one instead of her cook's. -->
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-ink/50" @click.self="$emit('close')">
        <div class="flex max-h-[94dvh] w-full max-w-lg flex-col rounded-t-[var(--radius-plate)] bg-plate" role="dialog" aria-modal="true" aria-labelledby="nearby-title">
            <div class="border-b border-rule p-4 pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="nearby-title" class="text-lg font-semibold">আশেপাশে আজ {{ meal.label }}</h2>
                        <p class="text-sm text-muted">অন্য গৃহিণীরা আজ যা রাঁধছেন। একটা বেছে নিলে আপনার এলাকা ম্যানেজারই পৌঁছে দেবেন।</p>
                    </div>
                    <button type="button" class="btn-quiet -mr-3 min-h-10 w-auto" @click="$emit('close')">বন্ধ</button>
                </div>

                <!-- Filters -->
                <label for="nearby-q" class="sr-only">খাবার বা রান্নাঘরের নাম</label>
                <input id="nearby-q" v-model.trim="q" type="search" class="field mt-3 text-base" placeholder="খুঁজুন: মাছ, খিচুড়ি, রান্নাঘরের নাম…" maxlength="60" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <label class="sr-only" for="nearby-price">দাম</label>
                    <select id="nearby-price" v-model="maxTaka" class="field min-h-10 w-auto flex-1 py-1 text-sm">
                        <option :value="null">যেকোনো দাম</option>
                        <option v-for="p in prices" :key="p" :value="p">৳{{ bn(p) }} পর্যন্ত</option>
                    </select>
                    <label class="sr-only" for="nearby-sort">সাজানো</label>
                    <select id="nearby-sort" v-model="sort" class="field min-h-10 w-auto flex-1 py-1 text-sm">
                        <option value="near">কাছে আগে</option>
                        <option value="cheap">কম দাম আগে</option>
                    </select>
                </div>
                <div v-if="tags.length" class="-mx-4 mt-2 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="ধরন">
                    <button
                        v-for="t in tags"
                        :key="t.slug"
                        type="button"
                        class="min-h-9 shrink-0 rounded-full border px-3 text-sm"
                        :class="picked.includes(t.slug) ? 'border-leaf bg-leaf text-white' : 'border-rule bg-plate'"
                        :aria-pressed="picked.includes(t.slug)"
                        @click="toggleTag(t.slug)"
                    >
                        {{ t.name }}
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4" aria-live="polite">
                <p v-if="loading" class="text-muted">খোঁজা হচ্ছে…</p>
                <p v-else-if="!posts.length" class="rounded-xl bg-steel p-4 text-sm">
                    {{ filtered ? 'এই ফিল্টারে কিছু পাওয়া যায়নি। ফিল্টার একটু কমিয়ে দেখুন।' : 'আশেপাশে আজ এই বেলায় অন্য কারো খাবার এখনো নেই।' }}
                    <br />না পেলে "কী খেতে চান লিখে জানান" দিয়ে এলাকা ম্যানেজারকে বলুন।
                </p>
                <p v-else class="mb-2 text-sm text-muted">{{ bn(posts.length) }}টা খাবার</p>

                <article v-for="p in posts" :key="p.id" class="mb-3 flex gap-3 rounded-xl border border-rule p-3">
                    <img v-if="p.images?.[0]" :src="p.images[0].url" alt="" class="size-20 shrink-0 rounded-lg object-cover" loading="lazy" />
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold leading-snug">{{ p.title }}</h3>
                        <p class="text-sm text-muted">
                            {{ p.kitchen.name }}<template v-if="p.kitchen.rating_avg"> · ★ {{ bn(p.kitchen.rating_avg) }}</template>
                        </p>
                        <p class="mt-0.5 text-sm">
                            <strong>{{ taka(p.price_paisa) }}</strong>
                            <template v-if="p.distance_m != null"> · {{ distanceText(p.distance_m) }} দূরে</template>
                            · {{ bn(p.plates_left) }} প্লেট বাকি
                        </p>
                        <p v-if="p.tags?.length" class="mt-0.5 truncate text-xs text-muted">{{ p.tags.map((t) => t.name).join(' · ') }}</p>
                        <button
                            v-if="confirming !== p.id"
                            type="button"
                            class="btn-primary mt-2 min-h-10 w-auto px-4 text-sm"
                            :disabled="busy"
                            @click="confirming = p.id"
                        >
                            এটা নেব
                        </button>
                        <div v-else class="mt-2 rounded-lg bg-turmeric-soft p-2 text-sm">
                            <p>আজ এই বেলায় আপনার গৃহিণীর খাবার আসবে না, এটা আসবে ({{ taka(p.price_paisa) }})। ঠিক আছে?</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="btn-primary min-h-10 w-auto px-4 text-sm" :disabled="busy" @click="pick(p)">হ্যাঁ, এটা নেব</button>
                                <button type="button" class="btn-quiet min-h-10 px-3 text-sm" @click="confirming = null">না</button>
                            </div>
                        </div>
                    </div>
                </article>
                <p v-if="error" class="error-text" role="alert">{{ error }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { distanceText, taka } from '@/lib/format';

const props = defineProps({ meal: { type: Object, required: true } });
const emit = defineEmits(['close', 'picked']);

const prices = [80, 100, 150, 200, 300];
const q = ref('');
const maxTaka = ref(null);
const sort = ref('near');
const picked = ref([]);
const tags = ref([]);
const posts = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const confirming = ref(null);

const filtered = computed(() => q.value !== '' || maxTaka.value !== null || picked.value.length > 0);

function toggleTag(slug) {
    picked.value = picked.value.includes(slug) ? picked.value.filter((s) => s !== slug) : [...picked.value, slug];
}

let timer;
let seq = 0;
async function load() {
    const mine = ++seq;
    loading.value = true;
    error.value = '';
    try {
        const params = { meal_slot: props.meal.slot, sort: sort.value };
        if (q.value) params.q = q.value;
        if (maxTaka.value) params.max_taka = maxTaka.value;
        if (picked.value.length) params['tags[]'] = picked.value;
        const { data } = await api.get('/regular/nearby', { params });
        if (mine === seq) posts.value = data.data;
    } catch (e) {
        if (mine === seq) error.value = errorMessage(e);
    } finally {
        if (mine === seq) loading.value = false;
    }
}

watch([maxTaka, sort, picked], load);
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 350);
});

async function pick(p) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/regular/choices', { meal_slot: props.meal.slot, choice: 'pick', post_id: p.id });
        emit('picked', data);
    } catch (e) {
        error.value = errorMessage(e);
        confirming.value = null;
        load();
    } finally {
        busy.value = false;
    }
}

onMounted(async () => {
    load();
    try {
        const { data } = await api.get('/tags');
        tags.value = (data.data ?? data).filter((t) => t.slug).slice(0, 20);
    } catch {
        tags.value = [];
    }
});
</script>
