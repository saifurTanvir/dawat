<template>
    <!-- F46: everything the cook does in her app, done for her by her shahidar. -->
    <section class="pt-4" aria-labelledby="kitchen-title">
        <RouterLink :to="{ name: 'shahidar' }" class="text-sm text-leaf">← আজকের তালিকা</RouterLink>
        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error && !data" class="error-text mt-6" role="alert">{{ error }}</p>

        <template v-else-if="data">
            <div class="mt-2 flex items-start justify-between gap-3">
                <div>
                    <h1 id="kitchen-title" class="font-serif text-2xl font-bold">{{ data.kitchen.name }}</h1>
                    <p class="text-sm text-muted">{{ data.kitchen.address }}</p>
                </div>
                <CallButtons :phone="data.kitchen.phone" :contact="data.kitchen.contact" />
            </div>
            <p class="mt-2 rounded-xl bg-turmeric-soft px-3 py-2 text-xs">এখানে যা করবেন, গৃহিণী সাথে সাথে নোটিফিকেশন পাবেন আর দাওয়াতের খাতায় আপনার নামে লেখা থাকবে।</p>
            <p v-if="notice" class="mt-3 rounded-xl bg-leaf-soft p-3 text-sm" role="status">{{ notice }}</p>
            <p v-if="error" class="error-text mt-3" role="alert">{{ error }}</p>

            <p v-if="!data.slots.length" class="mt-6 text-muted">আজ এই রান্নাঘরে কোনো বুকিং, পোস্ট বা মেনু নেই।</p>

            <div v-for="s in data.slots" :key="s.meal_slot" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">আজ {{ s.label }}</h2>
                    <span v-if="s.off" class="rounded-full bg-steel px-2 text-xs">রান্না নেই</span>
                </div>
                <p v-if="s.menu_today" class="text-xs text-muted">প্রতিদিনের মেনু: {{ s.menu_today }}</p>
                <p v-if="s.waiting.length" class="mt-1 text-sm">
                    এখনো পোস্ট আসেনি — {{ s.waiting.map((w) => `${w.name} (${bn(w.plates)})`).join(', ') }}
                </p>

                <!-- Posts: plates, name, sold out -->
                <div v-for="p in s.posts" :key="p.id" class="mt-3 rounded-xl border border-rule p-3">
                    <template v-if="editing === p.id">
                        <input v-model="editForm.title" class="field text-base" maxlength="100" />
                        <textarea v-model="editForm.note" class="field mt-2 text-base" rows="2" maxlength="300" placeholder="নোট (ইচ্ছা হলে)"></textarea>
                        <p class="mt-1 text-xs text-muted">দাম বদলাতে দাওয়াত টিমকে বলুন।</p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="btn-primary w-auto px-4" :disabled="busy" @click="saveEdit(p)">রাখুন</button>
                            <button type="button" class="btn-quiet w-auto" @click="editing = null">থাক</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold">{{ p.title }} · {{ taka(p.price_paisa) }}</p>
                            <button type="button" class="shrink-0 text-sm font-semibold text-leaf" @click="startEdit(p)">নাম ঠিক করুন</button>
                        </div>
                        <p class="text-xs text-muted">{{ p.source_label }} · {{ p.status_label }}</p>
                    </template>
                    <PlateSplitBar class="mt-2" :split="p.split" />
                    <div v-if="p.open" class="mt-2 flex items-center gap-2">
                        <div class="flex items-center rounded-xl border border-rule" role="group" :aria-label="`${p.title}: ফিডে কয় প্লেট বাকি`">
                            <button type="button" class="size-10 text-xl text-leaf disabled:text-rule" :disabled="busy || p.plates_left <= 0" aria-label="এক প্লেট কম" @click="plates(p, p.plates_left - 1)">−</button>
                            <output class="min-w-14 text-center text-sm">ফিডে {{ bn(p.plates_left) }}</output>
                            <button type="button" class="size-10 text-xl text-leaf disabled:text-rule" :disabled="busy || p.plates_left >= p.max_left" aria-label="এক প্লেট বেশি" @click="plates(p, p.plates_left + 1)">+</button>
                        </div>
                        <button v-if="p.plates_left > 0" type="button" class="btn-quiet w-auto text-sm" :disabled="busy" @click="soldOut(p)">শেষ হয়ে গেছে</button>
                    </div>
                </div>

                <!-- Ready -->
                <div v-if="s.can_ready || s.ready_at" class="mt-3 rounded-xl bg-leaf-soft p-3 text-sm">
                    <p v-if="s.ready_at">✅ রেডি জানানো হয়েছে {{ clockText(s.ready_at) }}<template v-if="s.ready_note"> · “{{ s.ready_note }}”</template></p>
                    <template v-if="readyFor === s.meal_slot">
                        <input v-model="readyNote" class="field mt-1 text-base" maxlength="200" placeholder="যেমন: ২টা পর্যন্ত বাসায়, তারপর দারোয়ানের কাছে" />
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="btn-primary w-auto px-4" :disabled="busy" @click="ready(s)">জানান</button>
                            <button type="button" class="btn-quiet w-auto" @click="readyFor = null">থাক</button>
                        </div>
                    </template>
                    <button v-else-if="s.can_ready" type="button" class="mt-1 font-semibold text-leaf" @click="readyFor = s.meal_slot; readyNote = ''">
                        {{ s.ready_at ? 'আবার রেডি জানান' : 'গৃহিণীর হয়ে "খাবার রেডি" জানান' }}
                    </button>
                </div>

                <!-- One-off orders -->
                <ul v-if="s.orders.length" class="mt-3 divide-y divide-rule text-sm">
                    <li v-for="o in s.orders" :key="o.id" class="py-2">
                        বক্স {{ o.code }} · {{ o.name }} · {{ o.kind }} · {{ bn(o.plates) }} প্লেট · {{ o.status_label }}
                        <span class="flex gap-4">
                            <button v-if="o.can_hand" type="button" class="font-semibold text-leaf" :disabled="busy" @click="orderAct(o, 'handed', 'ক্রেতা রান্নাঘর থেকে খাবার নিয়েছেন আর টাকা দিয়েছেন?')">ক্রেতা নিয়েছেন</button>
                            <button v-if="o.can_decline" type="button" class="font-semibold text-chili" :disabled="busy" @click="orderAct(o, 'decline', 'গৃহিণী এই অর্ডার দিতে পারছেন না? ক্রেতাকে জানানো হবে।')">দিতে পারছেন না</button>
                        </span>
                    </li>
                </ul>

                <!-- No cooking today -->
                <template v-if="!s.off">
                    <div v-if="offFor === s.meal_slot" class="mt-3">
                        <input v-model="offReason" class="field text-base" maxlength="200" placeholder="কেন রান্না নেই (যেমন: গৃহিণী অসুস্থ)" />
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="btn-primary w-auto bg-chili px-4" :disabled="busy || offReason.trim().length < 3" @click="off(s)">রান্না নেই লিখুন</button>
                            <button type="button" class="btn-quiet w-auto" @click="offFor = null">থাক</button>
                        </div>
                    </div>
                    <button v-else type="button" class="btn-quiet -ml-4 mt-1 text-chili" @click="offFor = s.meal_slot; offReason = ''">আজ {{ s.label }} রান্না নেই</button>
                </template>
            </div>

            <!-- Daily menus -->
            <div class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4">
                <h2 class="font-semibold">প্রতিদিনের মেনু</h2>
                <p v-if="!data.menus.length && !menuForm" class="mt-1 text-sm text-muted">কোনো মেনু নেই।</p>
                <div v-for="m in data.menus" :key="m.meal_slot" class="mt-2 flex items-start justify-between gap-2 text-sm">
                    <p><span class="font-semibold">{{ m.meal_slot_label }} · {{ m.title }}</span><span class="block text-xs text-muted">{{ m.price_label }} · {{ bn(m.plates_total) }} প্লেট · {{ m.post_at_label }}-এ · {{ m.weekdays_label }}</span></p>
                    <button type="button" class="shrink-0 font-semibold text-leaf" @click="editMenu(m)">বদলান</button>
                </div>
                <form v-if="menuForm" class="mt-3 space-y-2 border-t border-rule pt-3" @submit.prevent="saveMenu">
                    <select v-model="menuForm.meal_slot" class="field text-base" :disabled="menuForm.existing">
                        <option v-for="(label, key) in slotLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <input v-model="menuForm.title" class="field text-base" maxlength="100" placeholder="খাবার" />
                    <div class="grid grid-cols-3 gap-2">
                        <label class="text-xs text-muted">দাম (৳)<input v-model.number="menuForm.price_taka" class="field text-base" type="number" min="20" max="2000" /></label>
                        <label class="text-xs text-muted">প্লেট<input v-model.number="menuForm.plates_total" class="field text-base" type="number" min="1" max="20" /></label>
                        <label class="text-xs text-muted">কখন<input v-model="menuForm.post_at" class="field text-base" type="time" /></label>
                    </div>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <label v-for="(d, i) in days" :key="i" class="flex items-center gap-1 rounded-full border border-rule px-2 py-1"><input v-model="menuForm.weekdays" type="checkbox" :value="i" class="accent-leaf" /> {{ d }}</label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary w-auto px-4" :disabled="busy">রাখুন</button>
                        <button type="button" class="btn-quiet w-auto" @click="menuForm = null">থাক</button>
                        <button v-if="menuForm.existing" type="button" class="btn-quiet w-auto text-chili" :disabled="busy" @click="deleteMenu">মেনু সরান</button>
                    </div>
                </form>
                <button v-else-if="data.menus.length < 4" type="button" class="btn-quiet -ml-4 mt-1" @click="newMenu">+ মেনু যোগ করুন</button>
            </div>

            <!-- F47: what she sold this week and what customers said -->
            <div v-if="data.sales" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold">বিক্রি আর রিভিউ (৭ দিন)</h2>
                    <p class="text-sm"><span class="text-turmeric">★</span> {{ data.sales.rating_avg ? bn(data.sales.rating_avg.toFixed(1)) : '—' }} ({{ bn(data.sales.rating_count) }})</p>
                </div>
                <p class="text-xs text-muted">{{ bn(data.sales.totals.plates) }} প্লেট · গৃহিণীর পাওনা বাকি {{ taka(data.sales.totals.due_paisa) }}</p>
                <p v-for="d in data.sales.days.slice(0, 6)" :key="`${d.date}-${d.meal_slot}`" class="mt-1 text-xs">
                    {{ d.date.slice(5) }} {{ d.meal_slot_label }} · নিয়মিত {{ bn(d.regular_plates) }} · ফিড {{ bn(d.feed_plates) }} · পৌঁছেছে {{ bn(d.delivered) }}<template v-if="d.cancelled"> · বাতিল {{ bn(d.cancelled) }}</template>
                </p>
                <div class="mt-3 border-t border-rule pt-3">
                    <ReviewList :reviews="data.sales.reviews" :reply-path="(r) => `/shahidar/reviews/${r.id}/reply`" @replied="(m) => { notice = m; load(); }" />
                </div>
            </div>

            <!-- Today's log -->
            <div v-if="data.log.length" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4 text-sm">
                <h2 class="font-semibold">আজ গৃহিণীর হয়ে যা করা হয়েছে</h2>
                <p v-for="(l, i) in data.log" :key="i" class="mt-1"><span class="text-muted">{{ clockText(l.at) }}</span> · {{ l.who }} · {{ l.details }}</p>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import CallButtons from '@/components/CallButtons.vue';
import PlateSplitBar from '@/components/PlateSplitBar.vue';
import ReviewList from '@/components/ReviewList.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { clockText, taka } from '@/lib/format';

const route = useRoute();
const id = route.params.id;
const data = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const editing = ref(null);
const editForm = reactive({ title: '', note: '' });
const readyFor = ref(null);
const readyNote = ref('');
const offFor = ref(null);
const offReason = ref('');
const menuForm = ref(null);
const slotLabels = { breakfast: 'সকালের নাস্তা', lunch: 'দুপুর', snack: 'বিকেলের নাস্তা', dinner: 'রাত' };
const days = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];

async function load() {
    try {
        data.value = (await api.get(`/shahidar/kitchens/${id}`)).data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function run(request) {
    busy.value = true;
    error.value = '';
    try {
        const { data: r } = await request();
        notice.value = r.message ?? '';
        await load();
        return true;
    } catch (e) {
        error.value = errorMessage(e);
        return false;
    } finally {
        busy.value = false;
    }
}

const plates = (p, n) => run(() => api.patch(`/shahidar/posts/${p.id}`, { plates_left: n }));
const soldOut = (p) => run(() => api.post(`/shahidar/posts/${p.id}/sold-out`));

function startEdit(p) {
    editing.value = p.id;
    editForm.title = p.title;
    editForm.note = p.note ?? '';
}
async function saveEdit(p) {
    if (await run(() => api.patch(`/shahidar/posts/${p.id}`, { title: editForm.title, note: editForm.note }))) editing.value = null;
}

async function ready(s) {
    if (await run(() => api.post(`/shahidar/kitchens/${id}/ready`, { meal_slot: s.meal_slot, note: readyNote.value }))) readyFor.value = null;
}
async function off(s) {
    if (await run(() => api.post(`/shahidar/kitchens/${id}/off`, { meal_slot: s.meal_slot, reason: offReason.value }))) offFor.value = null;
}
function orderAct(o, path, question) {
    if (!window.confirm(question)) return;
    run(() => api.post(`/shahidar/kitchen-orders/${o.id}/${path}`));
}

function newMenu() {
    const free = Object.keys(slotLabels).find((k) => !data.value.menus.some((m) => m.meal_slot === k)) ?? 'lunch';
    menuForm.value = { meal_slot: free, title: '', price_taka: 120, plates_total: 5, post_at: '10:00', available_hours: 3, weekdays: [0, 1, 2, 3, 4, 5, 6], existing: false };
}
function editMenu(m) {
    menuForm.value = { ...m, weekdays: [...m.weekdays], existing: true };
}
async function saveMenu() {
    const { meal_slot, ...body } = menuForm.value;
    if (await run(() => api.put(`/shahidar/kitchens/${id}/menus/${meal_slot}`, { ...body, active: true }))) menuForm.value = null;
}
async function deleteMenu() {
    if (await run(() => api.delete(`/shahidar/kitchens/${id}/menus/${menuForm.value.meal_slot}`))) menuForm.value = null;
}

onMounted(load);
</script>
