<template>
    <!-- F64: a regular customer wants extra plates for guests. The cook never sees who. -->
    <section v-if="rows.length" class="mb-4 rounded-[var(--radius-plate)] bg-turmeric-soft p-5" aria-labelledby="guest-title">
        <h2 id="guest-title" class="font-semibold">অতিথির জন্য বাড়তি খাবার</h2>
        <div v-for="g in rows" :key="g.id" class="mt-3 border-t border-rule pt-3 first:mt-2 first:border-0 first:pt-0">
            <p class="font-semibold">{{ g.when }} · {{ bn(g.plates) }} প্লেট বাড়তি</p>
            <p v-if="g.note" class="text-sm">“{{ g.note }}”</p>
            <template v-if="g.can_reply">
                <p class="mt-1 text-sm text-muted">পারবেন? পারলে পোস্টে এই প্লেটগুলো বাড়তি রাখবেন।</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" class="min-h-11 rounded-xl bg-leaf px-3 font-semibold text-white disabled:opacity-50" :disabled="busy" @click="reply(g, true)">পারব</button>
                    <button type="button" class="min-h-11 rounded-xl border border-chili bg-plate px-3 font-semibold text-chili disabled:opacity-50" :disabled="busy" @click="reply(g, false)">পারব না</button>
                </div>
            </template>
            <p v-else class="mt-1 text-sm">{{ g.status === 'cook_yes' ? 'আপনি পারবেন বলেছেন — পোস্ট দিলে এই প্লেট বুক হয়ে যাবে।' : g.status_label }}</p>
        </div>
        <p v-if="message" class="mt-3 text-sm" role="status">{{ message }}</p>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';

const rows = ref([]);
const busy = ref(false);
const error = ref('');
const message = ref('');

onMounted(() => api.get('/my/guest-meals').then(({ data }) => (rows.value = data.data)).catch(() => {}));

async function reply(g, yes) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/my/guest-meals/${g.id}/reply`, { yes });
        rows.value = data.data;
        message.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
