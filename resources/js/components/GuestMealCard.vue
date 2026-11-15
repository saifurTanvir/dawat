<template>
    <!-- F64: extra plates for guests, asked of the cook through Dawat. -->
    <div class="rounded-[var(--radius-plate)] bg-plate p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">অতিথির খাবার</h2>
                <p class="text-sm text-muted">মেহমান আসবেন? বাড়তি প্লেট চেয়ে নিন।</p>
            </div>
            <button v-if="!open" type="button" class="btn-quiet min-h-10 shrink-0 px-3 text-sm font-semibold text-leaf" @click="start">অতিথির খাবার চাই</button>
        </div>

        <form v-if="open" class="mt-3 space-y-3" @submit.prevent="send">
            <fieldset>
                <legend class="mb-1 text-sm font-semibold">কবে</legend>
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="d in days" :key="d.value" type="button" :class="[btn, form.day === d.value ? on : off]" :aria-pressed="form.day === d.value" @click="form.day = d.value">{{ d.label }}</button>
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-1 text-sm font-semibold">কোন বেলা</legend>
                <div class="grid gap-2" :class="slots.length > 2 ? 'grid-cols-3' : 'grid-cols-2'">
                    <button v-for="s in slots" :key="s" type="button" :class="[btn, form.meal_slot === s ? on : off]" :aria-pressed="form.meal_slot === s" @click="form.meal_slot = s">{{ slotLabel[s] }}</button>
                </div>
            </fieldset>
            <div>
                <p class="mb-1 text-sm font-semibold" id="guest-plates">কয় প্লেট বাড়তি</p>
                <div class="flex items-center gap-3" role="group" aria-labelledby="guest-plates">
                    <button type="button" :class="[btn, off, 'w-11']" :disabled="form.plates <= 1" aria-label="এক প্লেট কম" @click="form.plates--">−</button>
                    <span class="min-w-8 text-center text-lg font-semibold">{{ bn(form.plates) }}</span>
                    <button type="button" :class="[btn, off, 'w-11']" :disabled="form.plates >= 10" aria-label="এক প্লেট বেশি" @click="form.plates++">+</button>
                </div>
            </div>
            <div>
                <label for="guest-note" class="mb-1 block text-sm font-semibold">কিছু বলবেন? <span class="font-normal text-muted">(ইচ্ছে হলে)</span></label>
                <input id="guest-note" v-model.trim="form.note" class="field text-base" maxlength="300" placeholder="যেমন: ঝাল কম, একজন বাচ্চা" />
            </div>
            <p class="text-xs text-muted">দাম গৃহিণীর পোস্টের দামে, প্লেট প্রতি। আলাদা বক্সে আসবে, টাকা ডেলিভারির সময় নগদে। গৃহিণী না পারলে আপনার শাহিদার অন্য রান্নাঘর থেকে ব্যবস্থা করবেন।</p>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary min-h-11 w-auto px-5" :disabled="busy || !form.meal_slot">অনুরোধ পাঠান</button>
                <button type="button" class="btn-quiet min-h-11 px-3" @click="open = false">থাক</button>
            </div>
        </form>

        <ul v-if="rows.length" class="mt-3">
            <li v-for="g in rows" :key="g.id" class="border-t border-rule pt-3 mt-3 first:mt-0">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-semibold">{{ g.when }} · {{ bn(g.plates) }} প্লেট</p>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="tone(g.status)">{{ g.status_label }}</span>
                </div>
                <p v-if="g.order_code" class="mt-1 text-sm">বক্স {{ g.order_code }} · {{ taka(g.total_paisa) }}, নগদে দেবেন</p>
                <p v-if="g.handled_note" class="mt-1 text-sm">{{ g.handled_note }}</p>
                <button v-if="g.can_cancel" type="button" class="mt-1 text-sm text-chili underline" :disabled="busy" @click="cancel(g)">লাগবে না</button>
            </li>
        </ul>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const props = defineProps({
    rows: { type: Array, required: true },
    slots: { type: Array, required: true },
});
const emit = defineEmits(['updated']);

const slotLabel = { breakfast: 'সকাল', lunch: 'দুপুর', snack: 'বিকেল', dinner: 'রাত' };
const days = [
    { value: 'today', label: 'আজ' },
    { value: 'tomorrow', label: 'কাল' },
];
const btn = 'min-h-11 rounded-xl border px-2 text-sm font-semibold disabled:opacity-40';
const on = 'border-leaf bg-leaf text-white';
const off = 'border-rule bg-plate text-ink';

const open = ref(false);
const busy = ref(false);
const error = ref('');
const form = reactive({ day: 'today', meal_slot: null, plates: 2, note: '' });

function start() {
    Object.assign(form, { day: 'today', meal_slot: props.slots[0] ?? null, plates: 2, note: '' });
    open.value = true;
}

function tone(status) {
    if (status === 'arranged') return 'bg-leaf-soft text-ink';
    if (status === 'cancelled') return 'bg-steel text-muted';
    return 'bg-turmeric-soft text-ink';
}

async function call(request) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await request();
        emit('updated', data);
        return true;
    } catch (e) {
        error.value = errorMessage(e);
        return false;
    } finally {
        busy.value = false;
    }
}

async function send() {
    if (await call(() => api.post('/regular/guest-meals', form))) open.value = false;
}

function cancel(g) {
    call(() => api.post(`/regular/guest-meals/${g.id}/cancel`));
}
</script>
