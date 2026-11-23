<template>
    <!-- F110: the evening return round — the office's empty boxes back to each cook, and her money on her pay day -->
    <section class="pt-4" aria-labelledby="return-title">
        <RouterLink to="/shahidar" class="text-sm font-semibold text-leaf">← আজকের তালিকা</RouterLink>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error && !round" class="error-text mt-6" role="alert">{{ error }}</p>

        <template v-else-if="round">
            <p class="mt-3 text-sm font-semibold text-muted"><span aria-hidden="true">🌙</span> ফেরত রাউন্ড · খালি বক্স আর গৃহিণীর টাকা</p>
            <h1 id="return-title" class="font-serif text-2xl font-bold">{{ round.office.name }}</h1>
            <p class="text-sm text-muted">{{ round.office.address }}</p>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-steel" aria-hidden="true">
                <div class="h-full rounded-full bg-leaf transition-all" :style="{ width: `${round.total ? (round.picked / round.total) * 100 : 0}%` }"></div>
            </div>
            <p class="mt-1 text-sm">
                {{ bn(round.picked) }} / {{ bn(round.total) }} জনের বক্স ফেরত দেওয়া হয়েছে
                <template v-if="round.paid_today_paisa"> · আজ দিয়েছেন {{ taka(round.paid_today_paisa) }}</template>
            </p>

            <p v-if="notice" class="mt-4 rounded-xl bg-leaf-soft p-3" role="status">{{ notice }}</p>
            <p v-if="error" class="mt-4 rounded-xl border-2 border-chili bg-chili/10 p-3 font-semibold text-chili" role="alert">{{ error }}</p>

            <!-- Before leaving the office -->
            <div v-if="!round.started" class="mt-4 rounded-[var(--radius-plate)] bg-turmeric-soft p-4">
                <p class="font-semibold">অফিস থেকে {{ bn(totalBoxes) }}টা খালি বক্স নিন, তারপর "রওনা দিচ্ছি" চাপুন।</p>
                <p class="mt-1 text-sm">
                    আজ গৃহিণীদের দিতে হবে মোট <strong>{{ taka(dueTotal) }}</strong> — আপনার হাতে জমা টাকা থেকে। প্রথম গৃহিণী সাথে সাথে খবর পাবেন, বাকিরা প্রায় ৩০ মিনিট আগে।
                </p>
                <p v-if="round.cash_held_paisa !== null" class="mt-2 text-sm">আপনার হাতে এখন দাওয়াতের {{ taka(Math.max(0, round.cash_held_paisa)) }}।</p>
                <p v-if="shortfall > 0" class="mt-2 rounded-lg border-2 border-chili bg-chili/10 px-3 py-2 text-sm font-semibold text-chili">
                    হাতের টাকায় হবে না — আরও {{ taka(shortfall) }} লাগবে। বের হওয়ার আগে দাওয়াত টিমের কাছ থেকে নিয়ে নিন।
                </p>
                <button type="button" class="btn-primary mt-3" :disabled="busy" @click="start">খালি বক্স নিয়েছি — রওনা দিচ্ছি</button>
            </div>

            <!-- The next house -->
            <article v-if="next" class="mt-4 rounded-[var(--radius-plate)] border-2 border-leaf bg-plate p-4" aria-labelledby="ret-next">
                <p class="text-sm font-semibold text-leaf">{{ round.started ? 'এখন যাবেন' : 'প্রথমে যাবেন' }} · {{ bn(next.position) }} নম্বর বাসা</p>
                <h2 id="ret-next" class="mt-1 text-xl font-bold">{{ next.name }}</h2>
                <p class="mt-1">{{ next.address || 'ঠিকানা নেই — ফোন করে জেনে নিন' }}</p>
                <p v-if="next.eta" class="mt-1 text-sm">পৌঁছাবেন আনুমানিক {{ next.eta }}</p>

                <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-xl bg-steel/60 p-3">
                        <p class="text-xs text-muted">ফেরত দেবেন</p>
                        <p class="text-xl font-bold">{{ bn(next.boxes) }}টা বক্স</p>
                    </div>
                    <div class="rounded-xl p-3" :class="next.pay_due_paisa ? 'bg-turmeric-soft' : 'bg-steel/60'">
                        <p class="text-xs text-muted">আজ টাকা দেবেন</p>
                        <p v-if="next.pay_due_paisa" class="text-xl font-bold">{{ taka(next.pay_due_paisa) }}</p>
                        <p v-else class="text-sm font-semibold">আজ নয় · উনি নেন {{ next.pay_label }}</p>
                    </div>
                </div>
                <p v-if="next.pay_due_paisa && next.pay_label !== 'প্রতিদিন'" class="mt-2 text-xs text-muted">
                    আজ ওনার টাকার দিন ({{ next.pay_label }}) — {{ bn(next.pay_orders) }}টা বক্সের পুরো পাওনা।
                </p>
                <p v-if="next.reply === 'ready'" class="mt-2 rounded-lg bg-leaf-soft px-3 py-1.5 text-sm font-semibold">✓ গৃহিণী জানিয়েছেন বাসায় আছেন</p>
                <p v-else-if="next.ready_at" class="mt-2 rounded-lg bg-turmeric-soft px-3 py-1.5 text-sm font-semibold">গৃহিণী {{ next.ready_at }}-এর পরে যেতে বলেছেন</p>

                <a v-if="next.map_url" :href="next.map_url" target="_blank" rel="noopener" class="btn-primary mt-3 flex items-center justify-center gap-2"
                    ><span aria-hidden="true">🗺️</span> পথ দেখান</a
                >
                <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-steel/50 px-3 py-2 text-sm">
                    <span>{{ next.phone }}</span>
                    <CallButtons :phone="next.phone" :contact="next.contact" />
                </div>
                <div v-if="round.started" class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                    <button type="button" class="btn-primary bg-leaf" :disabled="busy" @click="done(next)">
                        ✓ {{ next.pay_due_paisa ? `বক্স ও ${taka(next.pay_due_paisa)} দিয়েছি` : 'বক্স দিয়েছি' }}
                    </button>
                    <button type="button" class="btn-quiet border-2 border-rule px-4 font-semibold" :disabled="busy" @click="later(next)">পরে আসব</button>
                </div>
            </article>

            <!-- All done -->
            <article v-else-if="round.started" class="mt-4 rounded-[var(--radius-plate)] border-2 border-leaf bg-leaf-soft p-4">
                <h2 class="text-xl font-bold">সব বক্স ফেরত দেওয়া হয়েছে</h2>
                <p class="mt-1">
                    আজ গৃহিণীদের দিয়েছেন <strong>{{ taka(round.paid_today_paisa) }}</strong>।
                </p>
                <p class="mt-1 text-sm">
                    আপনার হাতে এখন দাওয়াতের <strong>{{ taka(Math.max(0, round.cash_held_paisa)) }}</strong>
                    <template v-if="round.cash_held_paisa < 0"> — উল্টো দাওয়াত আপনাকে {{ taka(-round.cash_held_paisa) }} দেবে</template>। দাওয়াত টিমকে বুঝিয়ে দিন।
                </p>
            </article>

            <h2 class="mt-6 text-lg font-semibold">সব বাসা</h2>
            <ol class="mt-2 space-y-2">
                <li v-for="s in round.stops" :key="s.id" class="rounded-xl bg-plate p-3" :class="s.picked ? 'opacity-70' : s.id === round.next_id ? 'ring-2 ring-leaf' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold">{{ bn(s.position) }}. {{ s.name }} <span v-if="s.picked" class="text-leaf">✓</span></p>
                        <a v-if="!s.picked && s.map_url" :href="s.map_url" target="_blank" rel="noopener" class="shrink-0 text-sm font-semibold text-leaf">ম্যাপ</a>
                    </div>
                    <p class="text-sm text-muted">
                        {{ bn(s.boxes) }}টা বক্স ·
                        <template v-if="s.picked">{{ s.paid_paisa ? `${taka(s.paid_paisa)} দিয়েছেন` : 'টাকা আজ নয়' }}</template>
                        <template v-else>{{ s.pay_due_paisa ? `আজ ${taka(s.pay_due_paisa)}` : `টাকা ${s.pay_label}` }}</template>
                    </p>
                    <p class="text-xs" :class="s.disputed ? 'font-semibold text-chili' : 'text-muted'">
                        <template v-if="s.picked">{{ s.confirmed ? '✓ গৃহিণী নিশ্চিত করেছেন' : s.disputed ? 'গৃহিণী বলছেন পাননি — ফোন করুন' : 'গৃহিণীর নিশ্চিত করা বাকি' }}</template>
                        <template v-else>
                            <template v-if="s.eta">আনুমানিক {{ s.eta }}</template>
                            <template v-if="s.told === 'now'"> · জানেন আপনি আসছেন</template>
                            <template v-else-if="s.told === 'soon'"> · ৩০ মিনিট আগের খবর পেয়েছেন</template>
                        </template>
                    </p>
                </li>
            </ol>
        </template>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import CallButtons from '@/components/CallButtons.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const route = useRoute();
const round = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const notice = ref('');
let timer = null;

const base = computed(() => `/shahidar/drops/${route.params.id}/return`);
const next = computed(() => round.value?.stops.find((s) => s.id === round.value.next_id) || null);
const totalBoxes = computed(() => (round.value?.stops || []).reduce((n, s) => n + s.boxes, 0));
const shortfall = computed(() => (round.value?.cash_held_paisa === null ? 0 : dueTotal.value - Math.max(0, round.value?.cash_held_paisa ?? 0)));
const dueTotal = computed(() => (round.value?.stops || []).reduce((n, s) => n + (s.picked ? 0 : s.pay_due_paisa || 0), 0));

async function load() {
    try {
        const { data } = await api.get(base.value);
        round.value = data.data;
        error.value = '';
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function send(path, body = {}) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`${base.value}${path}`, body);
        round.value = data.data;
        notice.value = data.message || '';
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

function here() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve({});
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolve({}),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 },
        );
    });
}

async function start() {
    busy.value = true;
    const where = await here();
    busy.value = false;
    await send('/start', where);
}

function done(stop) {
    const what = stop.pay_due_paisa ? `${bn(stop.boxes)}টা বক্স আর ${taka(stop.pay_due_paisa)}` : `${bn(stop.boxes)}টা বক্স`;
    if (!window.confirm(`${stop.name}-কে ${what} দিয়েছেন?`)) return;
    send(`/stops/${stop.id}/done`);
}

function later(stop) {
    send(`/stops/${stop.id}/later`);
}

onMounted(() => {
    load();
    timer = setInterval(() => !busy.value && load(), 60000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
