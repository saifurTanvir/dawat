<template>
    <!-- F95: the area manager is coming for office boxes — when, and her answer. F110: and bringing them back, with her money -->
    <section v-if="rows.length" class="mb-6 rounded-[var(--radius-plate)] border-2 border-turmeric bg-plate p-5" aria-labelledby="round-cook-title">
        <h2 id="round-cook-title" class="font-semibold">
            <span aria-hidden="true">{{ heading.icon }}</span> {{ heading.text }}
        </h2>
        <div v-for="(r, i) in rows" :key="r.id" class="mt-3" :class="i ? 'border-t border-rule pt-3' : ''">
            <!-- Coming to take today's boxes -->
            <template v-if="r.kind !== 'return'">
                <p v-if="r.coming_now" class="text-lg font-bold text-leaf">
                    এখন আপনার কাছে আসছেন<template v-if="r.eta"> · প্রায় {{ r.eta }}</template>
                </p>
                <p v-else-if="r.started && r.eta" class="text-lg font-bold">আনুমানিক {{ r.eta }}-এ আসবেন</p>
                <p v-else class="font-semibold">আজ {{ placeOf(r.meal_slot_label) }} বক্স নিতে আসবেন — রওনা দিলে সময় জানাবো</p>
                <p class="text-sm">
                    {{ r.meal_slot_label }} · অফিসের <strong>{{ bn(r.boxes) }}টা বক্স</strong> তৈরি রাখুন
                </p>
                <p v-if="r.reply === 'ready'" class="mt-2 rounded-lg bg-leaf-soft px-3 py-1.5 text-sm">✓ জানিয়েছেন বক্স তৈরি</p>
                <p v-else-if="r.ready_at" class="mt-2 rounded-lg bg-turmeric-soft px-3 py-1.5 text-sm">জানিয়েছেন {{ r.ready_at }}-এ তৈরি হবে — উনি আগে অন্য বাসায় যাবেন</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="btn-primary" :disabled="busy || r.reply === 'ready'" @click="reply(r, 'ready')">তৈরি আছি</button>
                    <button type="button" class="btn-quiet border-2 border-rule font-semibold" :disabled="busy" @click="reply(r, 'late')">আরও ১০ মিনিট লাগবে</button>
                </div>
            </template>

            <!-- Bringing the empty boxes back (and her money) -->
            <template v-else-if="!r.done">
                <p v-if="r.coming_now" class="text-lg font-bold text-leaf">
                    এখন খালি বক্স ফেরত দিতে আসছেন<template v-if="r.eta"> · প্রায় {{ r.eta }}</template>
                </p>
                <p v-else-if="r.started && r.eta" class="text-lg font-bold">আনুমানিক {{ r.eta }}-এ বক্স ফেরত দিতে আসবেন</p>
                <p v-else class="font-semibold">সন্ধ্যায় খালি বক্স ফেরত দিতে আসবেন</p>
                <p class="text-sm">
                    <strong>{{ bn(r.boxes) }}টা বক্স</strong> ফেরত ·
                    <template v-if="r.pay_due_paisa"
                        >আজ পাবেন <strong>{{ taka(r.pay_due_paisa) }}</strong></template
                    >
                    <template v-else>আপনার টাকা {{ r.pay_label }}</template>
                </p>
                <p v-if="r.reply === 'ready'" class="mt-2 rounded-lg bg-leaf-soft px-3 py-1.5 text-sm">✓ জানিয়েছেন বাসায় আছেন</p>
                <p v-else-if="r.ready_at" class="mt-2 rounded-lg bg-turmeric-soft px-3 py-1.5 text-sm">{{ r.ready_at }}-এর পরে আসতে বলেছেন — উনি আগে অন্য বাসায় যাবেন</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="btn-primary" :disabled="busy || r.reply === 'ready'" @click="reply(r, 'ready')">বাসায় আছি</button>
                    <button type="button" class="btn-quiet border-2 border-rule font-semibold" :disabled="busy" @click="reply(r, 'late')">১০ মিনিট পরে আসুন</button>
                </div>
            </template>

            <!-- He says he gave them: did she get them? -->
            <template v-else>
                <p class="font-semibold">
                    এলাকা ম্যানেজার জানিয়েছেন: <strong>{{ bn(r.boxes) }}টা বক্স</strong
                    ><template v-if="r.paid_paisa">
                        আর <strong>{{ taka(r.paid_paisa) }}</strong></template
                    >
                    দিয়েছেন।
                </p>
                <p v-if="r.confirmed" class="mt-2 rounded-lg bg-leaf-soft px-3 py-1.5 text-sm">✓ আপনি জানিয়েছেন পেয়েছেন</p>
                <p v-else-if="r.disputed" class="mt-2 rounded-lg bg-chili/10 px-3 py-1.5 text-sm font-semibold text-chili">জানিয়েছেন পাননি — দাওয়াত টিম ফোন করবে</p>
                <div v-if="!r.confirmed" class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="btn-primary" :disabled="busy" @click="confirm(r, true)">হ্যাঁ, পেয়েছি</button>
                    <button v-if="!r.disputed" type="button" class="btn-quiet border-2 border-rule font-semibold text-chili" :disabled="busy" @click="confirm(r, false)">
                        পাইনি / কম পেয়েছি
                    </button>
                </div>
            </template>
        </div>
        <p v-if="notice" class="mt-3 rounded-lg bg-leaf-soft p-3 text-sm" role="status">{{ notice }}</p>
        <p v-if="error" class="error-text mt-3" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn, placeOf } from '@/lib/bn';
import { taka } from '@/lib/format';

const rows = ref([]);
const busy = ref(false);
const notice = ref('');
const error = ref('');
let timer = null;

const heading = computed(() => {
    if (rows.value.some((r) => r.kind !== 'return')) return { icon: '🛵', text: 'এলাকা ম্যানেজার বক্স নিতে আসছেন' };
    if (rows.value.some((r) => !r.done)) return { icon: '🌙', text: 'এলাকা ম্যানেজার খালি বক্স ফেরত দিতে আসছেন' };
    return { icon: '🌙', text: 'খালি বক্স আর টাকা' };
});

async function confirm(r, got) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/my/rounds/${r.id}/confirm`, { got });
        rows.value = data.data;
        notice.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function load() {
    try {
        const { data } = await api.get('/my/rounds');
        rows.value = data.data;
    } catch {
        // Not a cook yet, or offline: nothing to show.
    }
}

async function reply(r, answer) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/my/rounds/${r.id}/reply`, {
            reply: answer,
        });
        rows.value = data.data;
        notice.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    load();
    timer = setInterval(load, 60000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
