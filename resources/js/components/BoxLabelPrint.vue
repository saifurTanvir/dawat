<template>
    <!-- F79: before leaving home — a named sticker for every box of today, two on each 75 × 100 label -->
    <section class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4" aria-labelledby="print-title">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="print-title" class="font-semibold"><span aria-hidden="true" class="mr-1">🖨️</span>বক্সের স্টিকার</h2>
                <p class="text-sm text-muted">
                    <template v-if="fresh">নতুন {{ bn(fresh) }}টা বক্সের স্টিকার বাকি।</template>
                    <template v-else-if="printed.length">আজকের সব বক্সের স্টিকার প্রিন্ট হয়েছে।</template>
                    <template v-else>বক্স এলে এখানে প্রিন্ট করবেন।</template>
                </p>
            </div>
            <button type="button" class="btn-primary min-h-10 w-auto shrink-0 px-4 text-sm" :disabled="busy || !fresh" @click="print">
                {{ busy ? 'তৈরি হচ্ছে…' : 'প্রিন্ট করুন' }}
            </button>
        </div>
        <p class="mt-1 text-xs text-muted">নাম, মোবাইল, ঠিকানা আর কল করার QR থাকে। অফিসের বক্সে "সারি · কলাম"। পিকআপে যার বক্স তার স্টিকার লাগিয়ে স্ক্যান করুন।</p>
        <ul v-if="printed.length" class="mt-2 space-y-1 text-sm">
            <li v-for="p in printed" :key="p.batch">
                {{ clockText(p.at) }}-এ {{ bn(p.count) }}টা ·
                <a :href="p.url" target="_blank" rel="noopener" class="font-semibold text-leaf underline">আবার খুলুন</a>
            </li>
        </ul>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { clockText } from '@/lib/format';

const props = defineProps({ orders: { type: Array, required: true } });
const emit = defineEmits(['printed']);

const busy = ref(false);
const error = ref('');
const printed = ref([]);

// Boxes still to go out that have no sticker yet.
const fresh = computed(() => props.orders.filter((o) => ['booked', 'picked_up'].includes(o.status) && !o.sticker).length);

onMounted(() => api.get('/shahidar/stickers/printed').then(({ data }) => (printed.value = data.data)).catch(() => {}));

async function print() {
    busy.value = true;
    error.value = '';
    // Opened now, filled after the reply — a tab opened later is blocked as a popup.
    const tab = window.open('', '_blank');
    try {
        const { data } = await api.post('/shahidar/stickers/print');
        printed.value = data.data.printed;
        if (tab) tab.location = data.data.url;
        else window.location.assign(data.data.url);
        emit('printed', data.message);
    } catch (e) {
        tab?.close();
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
