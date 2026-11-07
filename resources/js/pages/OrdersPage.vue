<template>
    <section class="pt-4" aria-labelledby="orders-title">
        <h1 id="orders-title" class="font-serif text-2xl font-bold">আমার খাবার</h1>

        <RouterLink to="/regular" class="mt-3 flex items-center justify-between rounded-[var(--radius-plate)] bg-plate p-4">
            <span>
                <span class="block font-semibold">প্রতিদিন ঘরের খাবার</span>
                <span class="text-sm text-muted">নিয়মিত বুকিং, আজকের বক্স, বিরতি</span>
            </span>
            <span aria-hidden="true" class="text-leaf">›</span>
        </RouterLink>

        <h2 class="mt-6 text-lg font-semibold">"নিতে চাই" অর্ডার</h2>
        <p v-if="loading" class="mt-2 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="!orders.length" class="mt-2 text-muted">এই সপ্তাহে কোনো অর্ডার নেই। ফিডে পছন্দের খাবারে "নিতে চাই" চাপুন।</p>
        <p v-if="notice" class="mt-3 rounded-xl bg-leaf-soft p-3" role="status">{{ notice }}</p>

        <div v-for="o in orders" :key="o.id" class="mt-3 rounded-[var(--radius-plate)] bg-plate p-4">
            <p class="font-semibold">{{ o.title }}</p>
            <p class="text-sm text-muted">{{ o.kitchen }} · {{ o.meal_slot_label }} · বক্স {{ o.code }}</p>
            <p class="mt-1">{{ bn(o.plates) }} প্লেট · {{ taka(o.total_paisa) }} · <span class="font-semibold">{{ o.status_label }}</span></p>
            <p class="mt-1 text-sm text-muted">{{ o.receive === 'self' ? 'নিজে নেবেন — গৃহিণী ফোন করবেন' : `পৌঁছাবে: ${o.address}` }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-x-4">
                <a v-if="o.shahidar && ['booked', 'picked_up'].includes(o.status)" :href="`tel:${o.shahidar.phone}`" class="font-semibold text-leaf">শাহিদারকে ফোন ({{ o.shahidar.name }})</a>
                <button v-if="o.can_cancel" type="button" class="font-semibold text-chili" :disabled="busy" @click="cancel(o)">বাতিল করুন</button>
            </div>
        </div>
        <p v-if="error" class="error-text mt-4" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const loading = ref(true);
const busy = ref(false);
const orders = ref([]);
const error = ref('');
const notice = ref('');

onMounted(async () => {
    try {
        const { data } = await api.get('/orders');
        orders.value = data.data;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

async function cancel(order) {
    if (!window.confirm(`বক্স ${order.code} বাতিল করবেন?`)) return;
    busy.value = true;
    try {
        const { data } = await api.post(`/orders/${order.id}/cancel`);
        orders.value.splice(orders.value.findIndex((o) => o.id === order.id), 1, data.data);
        notice.value = data.message;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
