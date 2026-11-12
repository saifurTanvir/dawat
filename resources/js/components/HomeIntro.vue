<template>
    <!-- F52, compact: a visitor who already picked a place gets the same three ways in, then their food. -->
    <section v-if="compact" aria-labelledby="home-title" class="pt-1">
        <h1 id="home-title" class="font-serif text-xl leading-snug font-bold text-leaf-deep">
            পাড়ার গৃহিণীদের ঘরের রান্না — খান, রোজ নিন, বা রেঁধে আয় করুন
        </h1>
        <nav aria-label="শুরু করুন" class="mt-3 grid grid-cols-3 gap-2">
            <a href="#feed-title" :class="TILE" @click.prevent="$emit('find-food')">
                <span :class="[ICON, 'bg-leaf-soft text-leaf']"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /></svg></span>
                আজকের খাবার কিনুন
            </a>
            <RouterLink to="/regular" :class="TILE">
                <span :class="[ICON, 'bg-leaf-soft text-leaf']"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4M8 14h2M14 14h2M8 17h2" /></svg></span>
                রোজ দুপুরের লাঞ্চ নিন
            </RouterLink>
            <RouterLink to="/cook" :class="TILE">
                <span :class="[ICON, 'bg-turmeric-soft text-leaf-deep']"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 13h16a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6Z" /><path d="M12 9V6M8 9V7M16 9V7M2 13h20" /></svg></span>
                রান্না করে আয় করুন
            </RouterLink>
        </nav>
        <details class="mt-2 text-[0.9375rem]">
            <summary class="cursor-pointer py-2 font-semibold text-leaf">কীভাবে চলে?</summary>
            <ol class="mt-1 space-y-2 leading-relaxed">
                <li v-for="(step, i) in steps" :key="i" class="flex gap-2.5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-leaf-soft font-bold">{{ bn(i + 1) }}</span>
                    <span>{{ step }}</span>
                </li>
            </ol>
        </details>
    </section>

    <!-- F52: the first screen for a new visitor: what Dawat is, and three ways in. -->
    <section v-else aria-labelledby="home-title" class="pt-1">
        <h1 id="home-title" class="font-serif text-[1.625rem] leading-snug font-bold text-leaf-deep">
            পাড়ার গৃহিণীদের ঘরের রান্না — খান, রোজ নিন, বা রেঁধে আয় করুন
        </h1>
        <p class="mt-2 text-muted">
            <template v-if="areas.length">ঢাকার {{ bn(areas.length) }}টা এলাকায় চলছে।</template>
            আপনি কী করতে এসেছেন?
        </p>

        <nav aria-label="শুরু করুন" class="mt-4 space-y-2.5">
            <a href="#feed-title" :class="DOOR" @click.prevent="$emit('find-food')">
                <img v-if="doorPhoto" :src="doorPhoto" alt="" @error="broken.add(doorPhoto)" :class="[ART, 'object-cover']" />
                <span v-else :class="[ART, 'bg-leaf-soft text-leaf']">
                    <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /></svg>
                </span>
                <span class="flex-1">
                    <strong class="block text-[1.0625rem]">আজকের খাবার কিনুন</strong>
                    <span class="block text-sm text-muted">{{ todayLine }}</span>
                </span>
                <Chevron />
            </a>

            <RouterLink to="/regular" :class="DOOR">
                <span :class="[ART, 'bg-leaf-soft text-leaf']">
                    <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4M8 14h2M14 14h2M8 17h2" /></svg>
                </span>
                <span class="flex-1">
                    <strong class="block text-[1.0625rem]">রোজ দুপুরের লাঞ্চ নিন</strong>
                    <span class="block text-sm text-muted">একবার বুক করুন, রোজ নিজে থেকে আসবে। টাকা সপ্তাহে বা মাসে।</span>
                </span>
                <Chevron />
            </RouterLink>

            <RouterLink to="/cook" :class="DOOR">
                <span :class="[ART, 'bg-turmeric-soft text-leaf-deep']">
                    <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 13h16a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6Z" /><path d="M12 9V6M8 9V7M16 9V7M2 13h20" /></svg>
                </span>
                <span class="flex-1">
                    <strong class="block text-[1.0625rem]">রান্না করে আয় করুন</strong>
                    <span class="block text-sm text-muted">গৃহিণীদের জন্য — পরিবারের রান্না থেকে কয়েক প্লেট বিক্রি</span>
                </span>
                <Chevron />
            </RouterLink>
        </nav>

        <ul v-if="trust.length" class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="দাওয়াত সম্পর্কে">
            <li v-for="t in trust" :key="t" class="rounded-full bg-plate px-3 py-1">{{ t }}</li>
        </ul>

        <div v-if="samples.length" class="mt-6">
            <h2 class="text-lg font-bold">{{ hasToday ? 'আজ যা রান্না হয়েছে' : 'সম্প্রতি যা রান্না হয়েছে' }}</h2>
            <ul class="-mx-4 mt-2 flex gap-2.5 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                <li v-for="(s, i) in samples" :key="i" class="w-40 shrink-0 overflow-hidden rounded-2xl bg-plate">
                    <component :is="s.id ? RouterLink : 'div'" v-bind="s.id ? { to: `/p/${s.id}` } : {}" class="block">
                        <img :src="s.image_url" :alt="s.title" class="h-24 w-full bg-leaf-soft object-cover" @error="broken.add(s.image_url)" loading="lazy" decoding="async" />
                        <div class="px-2.5 py-2">
                            <p class="truncate text-sm font-bold">{{ s.title }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ taka(s.price_paisa) }}<template v-if="s.area"> · {{ s.area }}</template>
                            </p>
                            <p v-if="!s.today" class="text-xs text-muted">আগের দিনের রান্না</p>
                        </div>
                    </component>
                </li>
            </ul>
        </div>

        <div class="mt-7">
            <h2 class="text-lg font-bold">কীভাবে চলে</h2>
            <ol class="mt-2 space-y-2.5 text-[0.9375rem] leading-relaxed">
                <li v-for="(step, i) in steps" :key="i" class="flex gap-2.5">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-leaf-soft font-bold">{{ bn(i + 1) }}</span>
                    <span>{{ step }}</span>
                </li>
            </ol>
        </div>
    </section>
</template>

<script setup>
import { computed, h, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const props = defineProps({
    areas: { type: Array, default: () => [] },
    // A returning visitor who already picked a place: headline and doors only.
    compact: { type: Boolean, default: false },
});
defineEmits(['find-food']);

const Chevron = () => h('svg', { viewBox: '0 0 24 24', class: 'size-5 shrink-0 text-leaf', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'aria-hidden': 'true' }, [h('path', { d: 'M9 6l6 6-6 6' })]);

const DOOR = 'flex items-center gap-3.5 rounded-[var(--radius-plate)] bg-plate p-3.5 text-ink shadow-[0_1px_0_var(--color-rule)] transition-colors hover:bg-leaf-soft/40';
const ART = 'grid size-[4.5rem] shrink-0 place-items-center rounded-[0.875rem]';
const TILE = 'flex flex-col items-center gap-1.5 rounded-2xl bg-plate px-2 py-3 text-center text-sm leading-snug font-semibold text-ink shadow-[0_1px_0_var(--color-rule)] hover:bg-leaf-soft/40';
const ICON = 'grid size-10 place-items-center rounded-xl';

const summary = ref(null);

// A photo that fails to load is dropped instead of showing an empty box.
const broken = reactive(new Set());
const samples = computed(() => (summary.value?.samples ?? []).filter((s) => s.image_url && !broken.has(s.image_url)));
const hasToday = computed(() => samples.value.some((s) => s.today));
const doorPhoto = computed(() => samples.value[0]?.image_url ?? null);

const todayLine = computed(() => {
    const t = summary.value?.today;
    if (t?.count) return `আশেপাশে আজ ${bn(t.count)}টা রান্না, ${taka(t.min_price_paisa)} থেকে`;
    return 'আশেপাশের গৃহিণীদের আজকের রান্না দেখুন';
});

const trust = computed(() => {
    const s = summary.value;
    if (!s) return [];
    const out = [];
    if (s.kitchens) out.push(`${bn(s.kitchens)} জন যাচাই করা গৃহিণী`);
    if (s.rating?.avg && s.rating.count >= 3) out.push(`★ ${bn(s.rating.avg)} গড় রেটিং`);
    out.push('টাকা খাবার হাতে পেয়ে');
    return out;
});

const steps = [
    'পাড়ার গৃহিণী রান্না পোস্ট দেন — দাওয়াত প্রত্যেক রান্নাঘর নিজে দেখে অনুমোদন দেয়।',
    'আপনি আজকের খাবার থেকে "নিতে চাই" চাপেন, বা রোজকার লাঞ্চ বুক করেন।',
    'শাহিদার (দাওয়াতের পাড়ার ডেলিভারি সাথী) খাবার বাসায় বা অফিসে পৌঁছে দেন।',
];

onMounted(async () => {
    if (props.compact) return; // no numbers or photos needed: the feed follows
    try {
        const { data } = await api.get('/home');
        summary.value = data.data;
    } catch {
        // The doors work without the numbers.
    }
});
</script>
