<template>
    <!-- F43: menus that post themselves every chosen day. -->
    <section class="mb-6 rounded-[var(--radius-plate)] bg-plate p-5" aria-labelledby="menu-title">
        <h2 id="menu-title" class="font-semibold">প্রতিদিনের মেনু</h2>
        <p class="text-sm text-muted">রোজ একই সময়ে নিজে থেকে পোস্ট হবে। ফোন খুলতে না পারলেও নিয়মিত গ্রাহকরা বক্স পাবেন।</p>

        <div v-for="m in menus" :key="m.meal_slot" class="mt-4 border-t border-rule pt-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ m.meal_slot_label }} · {{ m.title }}</p>
                    <p class="text-sm text-muted">{{ m.price_label }} · {{ bn(m.plates_total) }} প্লেট · {{ m.post_at_label }}-এ · {{ m.weekdays_label }}</p>
                    <p class="mt-1 text-sm" :class="m.today.includes('নিজে পোস্ট হবে') ? 'text-leaf' : 'text-muted'">{{ m.today }}</p>
                </div>
                <button type="button" class="shrink-0 font-semibold text-leaf" @click="edit(m)">বদলান</button>
            </div>
            <button
                v-if="m.today.includes('নিজে পোস্ট হবে')"
                type="button"
                class="btn-quiet -ml-4 mt-1 text-chili"
                :disabled="busy"
                @click="dayOff(m)"
            >আজ {{ m.meal_slot_label }} রান্না নেই</button>
        </div>

        <form v-if="form" class="mt-4 space-y-3 border-t border-rule pt-3" @submit.prevent="save">
            <label class="block text-sm">
                <span class="mb-1 block text-muted">কোন বেলা</span>
                <select v-model="form.meal_slot" class="field" :disabled="form.existing">
                    <option v-for="(label, key) in slots" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-muted">খাবার</span>
                <input v-model="form.title" class="field" maxlength="100" placeholder="যেমন: ভাত, মাছের ঝোল, ডাল" />
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="mb-1 block text-muted">প্লেটের দাম (৳)</span>
                    <input v-model.number="form.price_taka" class="field" inputmode="numeric" type="number" min="20" max="2000" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-muted">কয় প্লেট</span>
                    <input v-model.number="form.plates_total" class="field" inputmode="numeric" type="number" min="1" max="20" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-muted">কখন পোস্ট হবে</span>
                    <input v-model="form.post_at" class="field" type="time" min="06:00" max="22:00" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-muted">কতক্ষণ পাওয়া যাবে</span>
                    <select v-model.number="form.available_hours" class="field">
                        <option v-for="h in 6" :key="h" :value="h">{{ bn(h) }} ঘণ্টা</option>
                    </select>
                </label>
            </div>
            <fieldset class="text-sm">
                <legend class="mb-1 text-muted">কোন দিন</legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="(d, i) in days" :key="i" class="flex items-center gap-1 rounded-full border border-rule px-3 py-1">
                        <input v-model="form.weekdays" type="checkbox" :value="i" class="accent-leaf" /> {{ d }}
                    </label>
                </div>
            </fieldset>
            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-primary w-auto px-6" :disabled="busy">{{ busy ? 'রাখা হচ্ছে…' : 'রাখুন' }}</button>
                <button type="button" class="btn-quiet w-auto" @click="form = null">থাক</button>
                <button v-if="form.existing" type="button" class="btn-quiet w-auto text-chili" :disabled="busy" @click="remove">মেনু সরান</button>
            </div>
        </form>
        <button v-else-if="menus.length < 4" type="button" class="btn-quiet -ml-4 mt-2" @click="add">+ {{ menus.length ? 'আরেক বেলার মেনু' : 'মেনু যোগ করুন' }}</button>
        <p v-if="notice" class="mt-2 text-sm text-leaf" role="status">{{ notice }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';

const slots = { breakfast: 'সকালের নাস্তা', lunch: 'দুপুর', snack: 'বিকেলের নাস্তা', dinner: 'রাত' };
const days = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];
const menus = ref([]);
const form = ref(null);
const busy = ref(false);
const error = ref('');
const notice = ref('');

async function load() {
    try {
        menus.value = (await api.get('/my/menus')).data.data;
    } catch {
        menus.value = [];
    }
}

function add() {
    const free = Object.keys(slots).find((s) => !menus.value.some((m) => m.meal_slot === s)) ?? 'lunch';
    form.value = { meal_slot: free, title: '', price_taka: 120, plates_total: 5, post_at: free === 'dinner' ? '17:00' : '10:00', available_hours: 3, weekdays: [0, 1, 2, 3, 4, 5, 6], existing: false };
    error.value = '';
}

function edit(m) {
    form.value = { ...m, weekdays: [...m.weekdays], existing: true };
    error.value = '';
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const { meal_slot, ...body } = form.value;
        const { data } = await api.put(`/my/menus/${meal_slot}`, { ...body, active: true });
        menus.value = data.data;
        notice.value = data.message;
        form.value = null;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function remove() {
    busy.value = true;
    try {
        menus.value = (await api.delete(`/my/menus/${form.value.meal_slot}`)).data.data;
        form.value = null;
    } finally {
        busy.value = false;
    }
}

async function dayOff(m) {
    if (!window.confirm(`আজ ${m.meal_slot_label} রান্না নেই? আজকের মেনু পোস্ট হবে না, নিয়মিত গ্রাহকদের জানানো হবে।`)) return;
    busy.value = true;
    try {
        notice.value = (await api.post('/my/bookings/off', { meal_slot: m.meal_slot })).data.message;
        await load();
    } catch (e) {
        notice.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>
