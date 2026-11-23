<template>
    <!-- F95: the area manager is coming for office boxes — when, and her answer -->
    <section v-if="rows.length" class="mb-6 rounded-[var(--radius-plate)] border-2 border-turmeric bg-plate p-5" aria-labelledby="round-cook-title">
        <h2 id="round-cook-title" class="font-semibold"><span aria-hidden="true">🛵</span> এলাকা ম্যানেজার বক্স নিতে আসছেন</h2>
        <div v-for="(r, i) in rows" :key="r.id" class="mt-3" :class="i ? 'border-t border-rule pt-3' : ''">
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
        </div>
        <p v-if="notice" class="mt-3 rounded-lg bg-leaf-soft p-3 text-sm" role="status">
            {{ notice }}
        </p>
        <p v-if="error" class="error-text mt-3" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn, placeOf } from '@/lib/bn';

const rows = ref([]);
const busy = ref(false);
const notice = ref('');
const error = ref('');
let timer = null;

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
