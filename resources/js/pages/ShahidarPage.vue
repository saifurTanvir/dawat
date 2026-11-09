<template>
    <section class="pt-4" aria-labelledby="sh-title">
        <h1 id="sh-title" class="font-serif text-2xl font-bold">আজকের তালিকা</h1>
        <p class="text-muted">{{ dateLabel }}</p>
        <PushToggle off-title="নতুন বক্স বা দাম জিজ্ঞেস করার কাজ এলে ফোনে জানুন" />

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error" class="error-text mt-6" role="alert">{{ error }}</p>

        <template v-else>
            <p v-if="notice" class="mt-4 rounded-xl bg-leaf-soft p-3" role="status">{{ notice }}</p>

            <p v-if="day.cash_held_paisa" class="mt-4 rounded-xl bg-plate p-3">
                হাতে জমা টাকা <strong>{{ taka(day.cash_held_paisa) }}</strong> · দাওয়াত টিমকে বুঝিয়ে দিন
            </p>

            <!-- F38: what is waiting right now, meal by meal -->
            <div class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4">
                <p class="font-semibold">এখন: তুলতে {{ bn(pickupCount) }}টা বক্স · পৌঁছাতে {{ bn(onTheWay.length) }}টা<template v-if="asking.length"> · ফোন {{ bn(asking.length) }}টা</template></p>
                <p v-if="newCount" class="mt-1 text-sm font-semibold text-leaf">🆕 শেষবার দেখার পর নতুন {{ bn(newCount) }}টা বক্স</p>
                <div v-if="slotChips.length > 1" class="-mx-1 mt-3 flex flex-wrap gap-2" role="group" aria-label="বেলা">
                    <button
                        v-for="c in slotChips"
                        :key="c.value"
                        type="button"
                        class="min-h-10 rounded-full border px-4 text-sm font-semibold"
                        :class="slotFilter === c.value ? 'border-leaf bg-leaf text-white' : 'border-rule bg-plate text-ink'"
                        :aria-pressed="slotFilter === c.value"
                        @click="slotFilter = c.value"
                    >{{ c.label }} ({{ bn(c.count) }})</button>
                </div>
                <p class="mt-2 text-xs text-muted">তালিকা প্রতি মিনিটে নিজে হালনাগাদ হয় · শেষ {{ loadedAt ? clockText(loadedAt) : '' }}</p>
            </div>

            <!-- Cooks who have not posted yet -->
            <div v-if="day.waiting.length" class="mt-4 rounded-[var(--radius-plate)] bg-turmeric-soft p-4">
                <p class="font-semibold">এখনো পোস্ট আসেনি</p>
                <ul class="mt-1 space-y-1 text-sm">
                    <li v-for="(w, i) in day.waiting" :key="i">
                        {{ w.meal_slot_label }} · {{ w.kitchen }} → {{ w.customer }} ({{ bn(w.plates) }})
                        <CallButtons class="ml-1 inline-flex" :phone="w.kitchen_phone" :contact="w.kitchen_contact" />
                    </li>
                </ul>
            </div>

            <!-- F30: price above the customer's limit: call and ask -->
            <div v-if="asking.length" class="mt-4 rounded-[var(--radius-plate)] border-2 border-turmeric bg-plate p-4">
                <p class="font-semibold">ফোন করুন: আজ দাম বেশি</p>
                <div v-for="o in asking" :key="o.id" class="mt-3 border-t border-rule pt-3 first:border-0 first:pt-0">
                    <div class="flex items-start justify-between gap-3">
                        <p>
                            <span class="font-semibold">{{ o.customer.name }}</span> · {{ o.meal_slot_label }} · {{ o.kitchen.name }}
                            <span class="block text-sm text-muted">প্লেট {{ taka(o.price_paisa) }}, সীমা {{ taka(o.max_price_paisa) }} · {{ clockText(o.ask_until) }} পর্যন্ত</span>
                        </p>
                        <CallButtons :phone="o.customer.phone" :contact="o.customer.contact" />
                    </div>
                    <div class="mt-2 flex gap-4 text-sm">
                        <button type="button" class="font-semibold text-leaf" :disabled="busy" @click="act(o, 'accept')">নেবেন</button>
                        <button type="button" class="font-semibold text-chili" :disabled="busy" @click="act(o, 'skip', { reason: 'দাম বেশি, নেবেন না' })">নেবেন না</button>
                    </div>
                </div>
            </div>

            <!-- Pickups, cook by cook -->
            <h2 class="mt-6 text-lg font-semibold">পিকআপ</h2>
            <p v-if="!pickups.length" class="mt-1 text-muted">এখন তোলার মতো কিছু নেই।</p>
            <div v-for="g in pickups" :key="g.key" class="mt-3 rounded-[var(--radius-plate)] bg-plate p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold"><span class="text-leaf">{{ g.orders[0].meal_slot_label }}</span> · {{ g.kitchen.name }}</p>
                        <p class="text-sm text-muted">{{ g.kitchen.address }}</p>
                    </div>
                    <CallButtons :phone="g.kitchen.phone" :contact="g.kitchen.contact" />
                </div>
                <p v-if="g.ready" class="mt-2 rounded-lg bg-leaf-soft px-3 py-2 text-sm">
                    ✅ <strong>খাবার রেডি</strong> · {{ clockText(g.ready.ready_at) }}<template v-if="g.ready.ready_note"> · “{{ g.ready.ready_note }}”</template>
                </p>
                <p v-else class="mt-2 text-sm text-muted">গৃহিণী এখনো "রেডি" জানাননি।</p>
                <ul class="mt-2 divide-y divide-rule">
                    <li v-for="o in g.orders" :key="o.id" class="flex items-center justify-between gap-3 py-2">
                        <span>
                            <span class="font-semibold">বক্স {{ o.code }}</span>
                            <span v-if="isNew(o)" class="ml-1 rounded-full bg-leaf px-2 text-xs text-white">নতুন</span>
                            <span v-if="o.kind === 'oneoff'" class="ml-1 rounded-full bg-turmeric-soft px-2 text-xs">একবারের</span>
                            <span class="block text-sm text-muted">{{ o.meal_slot_label }} · {{ o.customer.name }} · {{ bn(o.plates) }} প্লেট</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <button type="button" class="btn-primary min-h-10 w-auto px-4" :disabled="busy" @click="startPickup(o)">পিকআপ</button>
                            <button type="button" class="text-xs text-muted underline" :disabled="busy" @click="skip(o)">আজ লাগবে না</button>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Deliveries -->
            <h2 class="mt-8 text-lg font-semibold">ডেলিভারি</h2>
            <p v-if="!onTheWay.length" class="mt-1 text-muted">পিকআপ করা বক্স এখানে আসবে।</p>
            <div v-for="o in onTheWay" :key="o.id" class="mt-3 rounded-[var(--radius-plate)] border-2 border-leaf bg-plate p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">বক্স {{ o.code }} · {{ o.customer.name }}<span v-if="o.kind === 'oneoff'" class="ml-1 rounded-full bg-turmeric-soft px-2 text-xs font-normal">একবারের</span></p>
                        <p class="text-sm text-muted">{{ o.customer.address }}</p>
                        <p v-if="o.customer.note" class="text-sm">{{ o.customer.note }}</p>
                        <p class="mt-1 text-sm">
                            {{ bn(o.plates) }} প্লেট ·
                            <template v-if="o.pays_by_bill"><strong>বিলে যোগ হবে — টাকা নেবেন না</strong></template>
                            <template v-else>নেবেন <strong>{{ taka(o.total_paisa) }}</strong></template>
                            <template v-if="o.sticker"> · স্টিকার {{ o.sticker }}</template>
                        </p>
                    </div>
                    <CallButtons :phone="o.customer.phone" :contact="o.customer.contact" />
                </div>

                <form v-if="handing === o.id" class="mt-3" @submit.prevent="deliver(o, code)">
                    <label :for="`code-${o.id}`" class="mb-1 block text-sm font-semibold">গ্রাহকের SMS-এর ৪ অঙ্কের কোড</label>
                    <div class="flex gap-2">
                        <input :id="`code-${o.id}`" v-model="code" class="field flex-1" inputmode="numeric" maxlength="4" autocomplete="one-time-code" />
                        <button type="submit" class="btn-primary w-auto px-5" :disabled="busy || code.length !== 4">দিয়েছি</button>
                    </div>
                    <label v-if="!o.pays_by_bill" class="mt-2 flex items-center gap-2 text-sm">
                        <input v-model="cash" type="checkbox" class="size-5 accent-leaf" />
                        {{ taka(o.total_paisa) }} নগদ পেয়েছি
                    </label>
                    <div class="mt-2 flex flex-wrap gap-x-4 text-sm">
                        <button type="button" class="font-semibold text-leaf" :disabled="busy" @click="deliver(o, '')">কোড ছাড়া দিয়েছি</button>
                        <button type="button" class="font-semibold text-chili" :disabled="busy" @click="fail(o)">পৌঁছানো যায়নি</button>
                    </div>
                    <p class="mt-1 text-xs text-muted">গ্রাহক নিজে বক্সের স্টিকার স্ক্যান করলেও চলবে।</p>
                </form>
                <button v-else type="button" class="btn-primary mt-3" @click="openHand(o)">হাতে দিচ্ছি</button>
            </div>

            <!-- Done -->
            <details v-if="finished.length" class="mt-8">
                <summary class="cursor-pointer font-semibold">শেষ হয়েছে ({{ bn(finished.length) }})</summary>
                <ul class="mt-2 divide-y divide-rule rounded-[var(--radius-plate)] bg-plate px-4">
                    <li v-for="o in finished" :key="o.id" class="py-2 text-sm">
                        বক্স {{ o.code }} · {{ o.customer.name }} · {{ o.status_label }}<template v-if="o.cancel_reason"> ({{ o.cancel_reason }})</template>
                        <template v-if="o.status === 'delivered' && o.pays_by_bill">
                            <span class="text-muted"> · বিলে যোগ হবে</span>
                        </template>
                        <template v-else-if="o.status === 'delivered'">
                            <span v-if="o.paid" class="text-muted"> · টাকা পেয়েছি</span>
                            <button v-else type="button" class="ml-1 font-semibold text-leaf" :disabled="busy" @click="act(o, 'paid')">{{ taka(o.total_paisa) }} পেয়েছি</button>
                        </template>
                    </li>
                </ul>
            </details>
        </template>

        <StickerScanner v-if="scanning" allow-skip @close="scanning = null" @found="finishPickup" />
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CallButtons from '@/components/CallButtons.vue';
import PushToggle from '@/components/PushToggle.vue';
import StickerScanner from '@/components/StickerScanner.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { clockText, taka } from '@/lib/format';

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const day = ref({ orders: [], waiting: [], date: '' });
const scanning = ref(null); // order being picked up
const handing = ref(null); // order id being handed over
const code = ref('');
const cash = ref(true);

const dateLabel = computed(() => day.value.date
    ? new Intl.DateTimeFormat('bn-BD', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${day.value.date}T00:00:00Z`))
    : '');

// F38: meal filter, newest boxes, cook's "ready".
const SLOT_ORDER = ['breakfast', 'lunch', 'snack', 'dinner'];
const slotFilter = ref('all');
const active = computed(() => day.value.orders.filter((o) => ['asking', 'booked', 'picked_up'].includes(o.status)));
const slotChips = computed(() => {
    const chips = [{ value: 'all', label: 'সব', count: active.value.length }];
    for (const s of SLOT_ORDER) {
        const list = active.value.filter((o) => o.meal_slot === s);
        if (list.length) chips.push({ value: s, label: list[0].meal_slot_label, count: list.length });
    }
    return chips;
});
const inSlot = (o) => slotFilter.value === 'all' || o.meal_slot === slotFilter.value;

const SEEN_KEY = 'dawat.shahidar.seenAt';
const seenBefore = ref(null);
function isNew(o) {
    return Boolean(seenBefore.value && o.created_at && new Date(o.created_at) > new Date(seenBefore.value));
}
const newCount = computed(() => active.value.filter(isNew).length);

const pickups = computed(() => {
    const groups = new Map();
    const booked = day.value.orders.filter((x) => x.status === 'booked' && inSlot(x))
        .sort((a, b) => SLOT_ORDER.indexOf(a.meal_slot) - SLOT_ORDER.indexOf(b.meal_slot));
    for (const o of booked) {
        const key = `${o.meal_slot}-${o.kitchen.id}`;
        if (!groups.has(key)) groups.set(key, { key, kitchen: o.kitchen, orders: [], ready: null });
        const g = groups.get(key);
        g.orders.push(o);
        if (o.ready_at && (!g.ready || o.ready_at > g.ready.ready_at)) g.ready = o;
    }
    // Ready kitchens first: that food is waiting.
    return [...groups.values()].sort((a, b) => Number(Boolean(b.ready)) - Number(Boolean(a.ready)));
});
const pickupCount = computed(() => day.value.orders.filter((o) => o.status === 'booked' && inSlot(o)).length);
const onTheWay = computed(() => day.value.orders.filter((o) => o.status === 'picked_up' && inSlot(o)));
const asking = computed(() => day.value.orders.filter((o) => o.status === 'asking' && inSlot(o)));
const finished = computed(() => day.value.orders.filter((o) => !['asking', 'booked', 'picked_up'].includes(o.status)));

const loadedAt = ref(null);
let timer = null;

async function load() {
    try {
        const { data } = await api.get('/shahidar/today');
        day.value = data.data;
        loadedAt.value = new Date().toISOString();
        error.value = '';
    } catch (e) {
        if (!day.value.date) error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

function refreshIfVisible() {
    // Don't redraw while the shahidar is typing a delivery code or scanning.
    if (document.visibilityState === 'visible' && !handing.value && !scanning.value && !busy.value) load();
}

onMounted(async () => {
    try {
        seenBefore.value = localStorage.getItem(SEEN_KEY);
    } catch {
        seenBefore.value = null;
    }
    await load();
    try {
        localStorage.setItem(SEEN_KEY, new Date().toISOString());
    } catch {
        // private mode: no "new" badges next time
    }
    timer = setInterval(refreshIfVisible, 60_000);
    document.addEventListener('visibilitychange', refreshIfVisible);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', refreshIfVisible);
});

async function refreshCash() {
    try {
        const { data } = await api.get('/shahidar/today');
        day.value.cash_held_paisa = data.data.cash_held_paisa;
    } catch {
        // the list still works without the total
    }
}

async function act(order, path, body = {}) {
    busy.value = true;
    notice.value = '';
    try {
        const { data } = await api.post(`/shahidar/orders/${order.id}/${path}`, body);
        const i = day.value.orders.findIndex((o) => o.id === order.id);
        if (i >= 0) day.value.orders.splice(i, 1, data.data);
        notice.value = `বক্স ${order.code}: ${data.message}`;
        if (path === 'paid') refreshCash();
        return true;
    } catch (e) {
        notice.value = `বক্স ${order.code}: ${errorMessage(e)}`;
        return false;
    } finally {
        busy.value = false;
    }
}

function startPickup(order) {
    scanning.value = order;
}

async function finishPickup(sticker) {
    const order = scanning.value;
    scanning.value = null;
    await act(order, 'pickup', { sticker: sticker || null });
}

function openHand(order) {
    handing.value = order.id;
    code.value = '';
    cash.value = true;
}

async function deliver(order, c) {
    if (!c && !window.confirm('কোড ছাড়া "দিয়েছি" দিলে দাওয়াত টিম পরে যাচাই করবে। ঠিক আছে?')) return;
    if (await act(order, 'deliver', { ...(c ? { code: c } : {}), cash_received: cash.value })) {
        handing.value = null;
        refreshCash();
    }
}

async function fail(order) {
    const reason = window.prompt('কী হয়েছিল? (যেমন: বাসায় কেউ নেই)');
    if (!reason) return;
    if (await act(order, 'fail', { reason })) handing.value = null;
}

function skip(order) {
    if (window.confirm(`বক্স ${order.code}: গ্রাহক আজ নেবেন না? প্লেট ফিডে ফিরে যাবে।`)) act(order, 'skip');
}
</script>
