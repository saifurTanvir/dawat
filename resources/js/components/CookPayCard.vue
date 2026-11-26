<template>
    <!-- F110: when she takes her money — every day, or once a week on her day — and what she is owed now -->
    <section v-if="pay" class="mb-6 rounded-[var(--radius-plate)] bg-plate p-5" aria-labelledby="pay-title">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="pay-title" class="font-semibold">আপনার টাকা</h2>
                <p class="text-sm text-muted">
                    এলাকা ম্যানেজার বাসায় এসে নগদে দেন · এখন নেন <strong class="text-ink">{{ pay.label }}</strong>
                </p>
            </div>
            <p class="shrink-0 text-right">
                <span class="block text-xs text-muted">এখন পাওনা</span>
                <span class="text-xl font-bold">{{ taka(pay.due_paisa) }}</span>
            </p>
        </div>
        <!-- Money the area manager says he gave at her door: did she get it? -->
        <div v-for="p in pay.payouts" :key="p.id" class="mt-3 rounded-xl border-2 p-3" :class="p.disputed ? 'border-chili' : 'border-turmeric'">
            <p class="font-semibold">
                এলাকা ম্যানেজার জানিয়েছেন: <strong>{{ taka(p.amount_paisa) }}</strong> দিয়েছেন ({{ bn(p.boxes) }}টা বক্সের)।
            </p>
            <p v-if="p.disputed" class="mt-1 text-sm font-semibold text-chili">জানিয়েছেন পাননি — দাওয়াত টিম ফোন করবে</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <button type="button" class="btn-primary" :disabled="busy" @click="confirmPayout(p, true)">হ্যাঁ, পেয়েছি</button>
                <button v-if="!p.disputed" type="button" class="btn-quiet border-2 border-rule font-semibold text-chili" :disabled="busy" @click="confirmPayout(p, false)">
                    পাইনি / কম পেয়েছি
                </button>
            </div>
        </div>
        <button v-if="!editing" type="button" class="mt-2 text-sm font-semibold text-leaf" @click="open">কখন নেবেন বদলান</button>
        <form v-else class="mt-3 space-y-3" @submit.prevent="save">
            <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 rounded-xl border-2 p-3" :class="cycle === 'daily' ? 'border-leaf bg-leaf-soft' : 'border-rule'">
                    <input v-model="cycle" type="radio" value="daily" class="accent-leaf" /> প্রতিদিন
                </label>
                <label class="flex items-center gap-2 rounded-xl border-2 p-3" :class="cycle === 'weekly' ? 'border-leaf bg-leaf-soft' : 'border-rule'">
                    <input v-model="cycle" type="radio" value="weekly" class="accent-leaf" /> সপ্তাহে একদিন
                </label>
            </div>
            <div v-if="cycle === 'weekly'">
                <label for="pay-day" class="mb-1 block text-sm font-semibold">কোন বারে নেবেন? সেদিন পুরো সপ্তাহের টাকা একসাথে।</label>
                <select id="pay-day" v-model="weekday" class="field">
                    <option :value="null" disabled>বার বেছে নিন</option>
                    <option v-for="(d, i) in pay.weekdays" :key="i" :value="i">{{ d }}</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary w-auto px-5" :disabled="busy || (cycle === 'weekly' && weekday === null)">রাখুন</button>
                <button type="button" class="btn-quiet" @click="editing = false">থাক</button>
            </div>
        </form>
        <p v-if="notice" class="mt-3 rounded-lg bg-leaf-soft p-3 text-sm" role="status">{{ notice }}</p>
        <p v-if="error" class="error-text mt-3" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const pay = ref(null);
const editing = ref(false);
const cycle = ref('daily');
const weekday = ref(null);
const busy = ref(false);
const notice = ref('');
const error = ref('');

async function confirmPayout(p, got) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/my/payouts/${p.id}/confirm`, { got });
        pay.value = data.data;
        notice.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

function open() {
    cycle.value = pay.value.pay_cycle;
    weekday.value = pay.value.pay_weekday;
    editing.value = true;
    notice.value = '';
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.put('/my/pay-schedule', { pay_cycle: cycle.value, pay_weekday: cycle.value === 'weekly' ? weekday.value : null });
        pay.value = data.data;
        notice.value = data.message;
        editing.value = false;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

onMounted(async () => {
    try {
        pay.value = (await api.get('/my/pay-schedule')).data.data;
    } catch {
        // Not a cook yet: nothing to show.
    }
});
</script>
