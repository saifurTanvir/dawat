<template>
    <!-- F61: tell the regular customer today's dish, before cooking. -->
    <section class="mb-6 rounded-[var(--radius-plate)] bg-plate p-5" aria-labelledby="notice-title">
        <h2 id="notice-title" class="font-semibold">আজ কী রান্না {{ forCook ? 'করছেন' : 'হচ্ছে' }}?</h2>
        <p class="mt-1 text-sm text-muted">{{ forCook ? 'শুধু নিয়মিত গ্রাহককে আগে থেকে জানানো — এটা পোস্ট নয়। বিক্রি করতে রান্না হলে "আজকের রান্না পোস্ট করুন" চাপুন।' : 'গৃহিণীর নিয়মিত গ্রাহক আগে থেকে জানবেন।' }}</p>

        <ul v-if="told.length" class="mt-3 space-y-1 text-sm">
            <li v-for="r in told" :key="r.meal_slot" class="flex items-start justify-between gap-2">
                <span><span class="text-muted">{{ r.label }}:</span> <strong>{{ r.title }}</strong> <span v-if="r.source" class="text-xs text-muted">· {{ r.source }}</span></span>
                <button type="button" class="shrink-0 text-leaf" @click="edit(r)">বদলান</button>
            </li>
        </ul>

        <form class="mt-3 space-y-2" @submit.prevent="save">
            <div class="flex flex-wrap gap-2" role="group" aria-label="বেলা">
                <ChoiceChip v-for="r in rows" :key="r.meal_slot" v-model="slot" :value="r.meal_slot" name="notice-slot">{{ r.label }}</ChoiceChip>
            </div>
            <input v-model.trim="title" class="field text-base" maxlength="150" placeholder="যেমন: খিচুড়ি, ডিম ভুনা, বেগুন ভাজি" aria-label="কী রান্না হবে" />
            <button type="submit" class="btn-primary" :disabled="busy || title.length < 2">{{ busy ? 'জানানো হচ্ছে…' : 'জানিয়ে দিন' }}</button>
            <p v-if="message" class="text-sm text-leaf" role="status">{{ message }}</p>
            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        </form>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import ChoiceChip from '@/components/ChoiceChip.vue';
import { api, errorMessage } from '@/lib/api';

const props = defineProps({
    rows: { type: Array, required: true },
    // '/my/menu-notices' for the cook; '/shahidar/kitchens/{id}/menu-notices' for her shahidar.
    endpoint: { type: String, required: true },
    forCook: { type: Boolean, default: true },
});
const emit = defineEmits(['saved']);

const slot = ref('lunch');
const title = ref('');
const busy = ref(false);
const message = ref('');
const error = ref('');
const told = computed(() => props.rows.filter((r) => r.title));

watch(slot, (s) => {
    title.value = props.rows.find((r) => r.meal_slot === s)?.title ?? '';
});

function edit(r) {
    slot.value = r.meal_slot;
    title.value = r.title;
}

async function save() {
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        const { data } = await api.put(`${props.endpoint}/${slot.value}`, { title: title.value });
        message.value = data.message;
        emit('saved', data.data);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
