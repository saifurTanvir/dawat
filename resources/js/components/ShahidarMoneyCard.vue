<template>
    <!-- F110: today's money in one place — collected, paid to cooks, to hand to Dawat — and the cooks to pay at their door -->
    <section
        v-if="money && (money.collected_paisa || money.paid_cooks_paisa || money.cooks.length)"
        class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4"
        aria-labelledby="money-title"
    >
        <h2 id="money-title" class="font-semibold">আজকের হিসাব</h2>
        <div class="mt-2 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-steel/60 p-2">
                <p class="text-xs text-muted">তুলেছেন</p>
                <p class="font-bold">{{ taka(money.collected_paisa) }}</p>
            </div>
            <div class="rounded-xl bg-steel/60 p-2">
                <p class="text-xs text-muted">গৃহিণীদের দিয়েছেন</p>
                <p class="font-bold">{{ taka(money.paid_cooks_paisa) }}</p>
            </div>
            <div class="rounded-xl p-2" :class="money.to_dawat_paisa < 0 ? 'bg-chili/10' : 'bg-leaf-soft'">
                <p class="text-xs text-muted">{{ money.to_dawat_paisa < 0 ? 'দাওয়াত আপনাকে দেবে' : 'দাওয়াতকে দেবেন' }}</p>
                <p class="font-bold">{{ taka(Math.abs(money.to_dawat_paisa)) }}</p>
            </div>
        </div>

        <template v-if="money.cooks.length">
            <h3 class="mt-4 text-sm font-semibold">গৃহিণীদের পাওনা</h3>
            <ul class="mt-1 divide-y divide-rule">
                <li v-for="c in money.cooks" :key="c.kitchen_id" class="flex items-center justify-between gap-3 py-2">
                    <span>
                        <span class="font-semibold">{{ c.name }}</span>
                        <span class="block text-xs text-muted">{{ bn(c.orders) }}টা বক্সের · টাকা নেন {{ c.pay_label }}</span>
                    </span>
                    <button v-if="c.pay_today" type="button" class="btn-primary min-h-10 w-auto shrink-0 px-3 text-sm" :disabled="busy" @click="pay(c)">
                        {{ taka(c.due_paisa) }} হাতে দিয়েছি
                    </button>
                    <span v-else class="shrink-0 text-right text-sm">
                        <strong>{{ taka(c.due_paisa) }}</strong>
                        <span class="block text-xs text-muted">আজ নয়</span>
                    </span>
                </li>
            </ul>
            <p class="mt-1 text-xs text-muted">টাকা যায় আপনার হাতে জমা টাকা থেকে; গৃহিণী অ্যাপে নিশ্চিত করবেন।</p>
        </template>
        <p v-if="notice" class="mt-2 rounded-lg bg-leaf-soft p-2 text-sm" role="status">{{ notice }}</p>
        <p v-if="error" class="mt-2 rounded-lg border-2 border-chili bg-chili/10 p-2 text-sm font-semibold text-chili" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

defineProps({ money: { type: Object, default: null } });
const emit = defineEmits(['updated']);
const busy = ref(false);
const notice = ref('');
const error = ref('');

async function pay(c) {
    if (!window.confirm(`${c.name}-কে ${taka(c.due_paisa)} হাতে দিয়েছেন?`)) return;
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/shahidar/kitchens/${c.kitchen_id}/pay`);
        emit('updated', data.data);
        notice.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
