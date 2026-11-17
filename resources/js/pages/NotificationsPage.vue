<template>
    <section class="pt-2" aria-labelledby="n-title">
        <div class="flex items-center justify-between gap-3">
            <h1 id="n-title" class="font-serif text-2xl font-bold">নোটিফিকেশন</h1>
            <button v-if="store.unread" type="button" class="btn-quiet -mr-4 min-h-10 w-auto px-4 text-sm" :disabled="busy" @click="readAll">সব পড়া হয়েছে</button>
        </div>

        <!-- Filter by part of the app, when there is more than one -->
        <div v-if="sections.length > 1" class="-mx-1 mt-3 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="কিসের নোটিফিকেশন">
            <button
                v-for="s in [{ key: 'all', label: 'সব', icon: '' }, ...sections]"
                :key="s.key"
                type="button"
                class="min-h-9 shrink-0 rounded-full border px-3 text-sm font-semibold"
                :class="filter === s.key ? 'border-leaf bg-leaf text-white' : 'border-rule bg-plate text-ink'"
                :aria-pressed="filter === s.key"
                @click="filter = s.key"
            >{{ s.icon ? s.icon + ' ' : '' }}{{ s.label }}</button>
        </div>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error" class="error-text mt-6" role="alert">{{ error }}</p>
        <div v-else-if="!store.items?.length" class="mt-6 rounded-[var(--radius-plate)] bg-plate p-5">
            <p class="font-semibold">এখনো কোনো নোটিফিকেশন নেই</p>
            <p class="mt-1 text-sm text-muted">নতুন অর্ডার, বক্স পথে, বিল, অনুরোধ — সব এখানে জমা থাকবে। চাপলে সংশ্লিষ্ট পাতা খুলবে।</p>
        </div>

        <template v-else>
            <div v-for="group in groups" :key="group.label" class="mt-5">
                <h2 class="text-sm font-semibold text-muted">{{ group.label }}</h2>
                <ul class="mt-2 overflow-hidden rounded-[var(--radius-plate)] bg-plate">
                    <li v-for="n in group.items" :key="n.id" class="border-t border-rule first:border-0">
                        <button type="button" class="flex w-full items-start gap-3 p-4 text-left" :class="n.read ? '' : 'bg-leaf-soft/50'" :disabled="busy" @click="open(n)">
                            <span class="mt-0.5 text-xl leading-none" aria-hidden="true">{{ n.icon }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2 text-xs text-muted">
                                    <span>{{ n.section_label }}</span>
                                    <span class="shrink-0">{{ agoText(n.at) }}</span>
                                </span>
                                <span class="mt-0.5 block" :class="n.read ? 'font-medium' : 'font-bold'">{{ n.title }}</span>
                                <span v-if="n.body" class="mt-0.5 block text-sm text-muted">{{ n.body }}</span>
                                <span class="mt-1 block text-sm font-semibold text-leaf">{{ openLabel(n) }} ›</span>
                            </span>
                            <span v-if="!n.read" class="mt-2 size-2.5 shrink-0 rounded-full bg-chili" aria-label="নতুন"></span>
                        </button>
                    </li>
                </ul>
            </div>
            <p class="mt-4 text-center text-xs text-muted">শেষ ৬০ দিনের নোটিফিকেশন থাকে।</p>
        </template>

        <PushToggle off-title="এগুলো ফোনেও পেতে চান?" on-text="নতুন নোটিফিকেশন ফোনেও আসবে।" />
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import PushToggle from '@/components/PushToggle.vue';
import { errorMessage } from '@/lib/api';
import { agoText } from '@/lib/format';
import { goTo, useNotificationsStore } from '@/stores/notifications';

const store = useNotificationsStore();
const router = useRouter();
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const filter = ref('all');

const OPEN = {
    shahidar: 'আজকের তালিকা খুলুন',
    cook: 'রান্নাঘর খুলুন',
    regular: 'নিয়মিত খাবার খুলুন',
    orders: 'আমার অর্ডার খুলুন',
    history: 'যা পেয়েছি খুলুন',
    admin: 'অ্যাডমিন প্যানেলে দেখুন',
};
function openLabel(n) {
    if (n.url.startsWith('/shahidar/kitchens/')) return 'রান্নাঘরের পাতা খুলুন';
    return OPEN[n.section] || 'খুলুন';
}

const sections = computed(() => {
    const seen = new Map();
    for (const n of store.items ?? []) if (!seen.has(n.section)) seen.set(n.section, { key: n.section, label: n.section_label, icon: n.icon });
    return [...seen.values()];
});

function dayKey(iso) {
    return new Date(iso).toLocaleDateString('en-CA', { timeZone: 'Asia/Dhaka' });
}
const groups = computed(() => {
    const list = (store.items ?? []).filter((n) => filter.value === 'all' || n.section === filter.value);
    const today = dayKey(new Date().toISOString());
    const yesterday = dayKey(new Date(Date.now() - 864e5).toISOString());
    const out = [];
    for (const n of list) {
        const k = dayKey(n.at);
        const label = k === today ? 'আজ' : k === yesterday ? 'গতকাল' : 'আগে';
        let g = out.find((x) => x.label === label);
        if (!g) out.push((g = { label, items: [] }));
        g.items.push(n);
    }
    return out;
});

onMounted(async () => {
    try {
        await store.load();
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

async function open(n) {
    busy.value = true;
    try {
        const url = await store.open(n.id);
        await goTo(router, url);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function readAll() {
    busy.value = true;
    try {
        await store.readAll();
    } finally {
        busy.value = false;
    }
}
</script>
