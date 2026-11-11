<template>
    <!-- F51: always visible while using someone else's app. -->
    <div v-if="imp" class="sticky top-0 z-40 flex items-center justify-between gap-3 bg-chili px-4 py-2 text-sm text-white" role="status">
        <p>
            আপনি এখন <strong>{{ auth.user.name || auth.user.phone }}</strong>-এর অ্যাপে
            <span class="opacity-80">· {{ clockText(imp.ends_at) }} পর্যন্ত · যা করবেন খাতায় থাকবে, তিনি জানবেন</span>
        </p>
        <button type="button" class="shrink-0 rounded-full bg-white px-3 py-1 font-semibold text-chili" :disabled="busy" @click="back">ফিরে যান</button>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { clockText } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const busy = ref(false);
const imp = computed(() => auth.user?.impersonation ?? null);

async function back() {
    busy.value = true;
    const hadOwn = await auth.endImpersonation();
    busy.value = false;
    if (hadOwn) {
        router.push(auth.user?.roles?.includes('shahidar') ? '/shahidar' : '/');
    } else {
        // Opened from the admin panel: nothing to go back to on this tab.
        window.close();
        router.push('/');
    }
}
</script>
