<template>
    <!-- F37: one card to turn on free phone notifications for this screen's role. -->
    <div v-if="state !== 'hidden'" class="mt-4 rounded-xl px-4 py-3" :class="state === 'on' ? 'bg-leaf-soft' : 'bg-turmeric-soft'">
        <template v-if="state === 'on'">
            <p class="text-sm"><span aria-hidden="true">🔔</span> এই ফোনে নোটিফিকেশন চালু আছে। {{ onText }}</p>
            <button type="button" class="mt-1 text-sm text-muted underline" @click="turnOff">বন্ধ করুন</button>
        </template>
        <template v-else-if="state === 'denied'">
            <p class="text-sm">🔕 নোটিফিকেশনের অনুমতি বন্ধ আছে। ফোনের সেটিংস → অ্যাপ/সাইট → দাওয়াত → Notifications চালু করুন।</p>
        </template>
        <template v-else>
            <p class="font-semibold"><span aria-hidden="true">🔔</span> {{ offTitle }}</p>
            <p class="mt-0.5 text-sm text-muted">ফ্রি — SMS না, ইন্টারনেট দিয়ে আসে।</p>
            <button type="button" class="btn-primary mt-2" :disabled="busy" @click="turnOn">{{ busy ? 'চালু হচ্ছে…' : 'নোটিফিকেশন চালু করুন' }}</button>
            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
            <p v-if="iosHint" class="mt-2 text-xs text-muted">iPhone-এ আগে দাওয়াত অ্যাপ হোম স্ক্রিনে যোগ করুন (<RouterLink to="/app" class="underline">কীভাবে</RouterLink>), তারপর অ্যাপ থেকে চালু করুন।</p>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { errorMessage } from '@/lib/api';
import { install } from '@/lib/pwa';
import { disablePush, enablePush, pushActive, pushPermission, pushSupported } from '@/lib/push';

defineProps({
    offTitle: { type: String, default: 'নতুন অর্ডার এলে ফোনে জানুন' },
    onText: { type: String, default: 'নতুন কিছু এলে ফোনে জানাবে।' },
});

const active = ref(false);
const busy = ref(false);
const error = ref('');
const permission = ref(pushPermission());
const iosHint = computed(() => install.platform === 'ios' && !install.standalone);

const state = computed(() => {
    if (active.value) return 'on';
    if (!pushSupported() && !iosHint.value) return 'hidden';
    if (permission.value === 'denied') return 'denied';
    return 'off';
});

async function turnOn() {
    busy.value = true;
    error.value = '';
    try {
        error.value = await enablePush();
        active.value = !error.value;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        permission.value = pushPermission();
        busy.value = false;
    }
}

async function turnOff() {
    await disablePush();
    active.value = false;
}

onMounted(async () => {
    active.value = await pushActive();
});
</script>
