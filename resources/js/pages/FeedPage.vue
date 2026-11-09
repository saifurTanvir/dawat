<template>
    <section aria-labelledby="feed-title" class="pt-2">
        <!-- F34: the full Dawat logo greets everyone on the home page. -->
        <img
            src="/brand/lockup.png"
            alt="দাওয়াত — পাড়ার ঘরের খাবার। পুরো পরিবার এক টেবিলে, পাশের বাসার রান্না"
            width="800"
            height="761"
            class="mx-auto mb-4 w-56"
        />

        <!-- Where we are looking from -->
        <div class="flex items-end justify-between gap-3">
            <div>
                <h1 id="feed-title" class="font-serif text-2xl font-bold">আজকের দাওয়াত</h1>
                <p class="mt-0.5 text-muted">{{ placeLabel }}</p>
            </div>
            <button v-if="feed.mode" type="button" class="btn-quiet -mr-4 min-h-10 shrink-0" @click="changePlace">
                জায়গা বদলান
            </button>
        </div>

        <!-- First visit: choose how to find food -->
        <div v-if="!feed.mode || choosing" class="mt-6 rounded-[var(--radius-plate)] bg-plate p-5">
            <p class="font-semibold">কোথাকার খাবার দেখবেন?</p>
            <button type="button" class="btn-primary mt-4" :disabled="locating" @click="useMyLocation">
                {{ locating ? 'লোকেশন খোঁজা হচ্ছে…' : 'আমার কাছের খাবার দেখুন' }}
            </button>
            <p class="mt-2 text-sm text-muted">আপনার লোকেশন শুধু দূরত্ব হিসাব করতে লাগে, কোথাও সংরক্ষণ হয় না।</p>
            <p v-if="locateError" class="error-text" role="alert">{{ locateError }}</p>

            <label for="area" class="mt-6 mb-2 block font-semibold">অথবা এলাকা বেছে নিন</label>
            <select id="area" class="field" :value="feed.areaSlug ?? ''" @change="pickArea($event.target.value)">
                <option value="" disabled>এলাকা</option>
                <option v-for="a in areas" :key="a.slug" :value="a.slug">{{ a.name }}</option>
            </select>
        </div>

        <template v-else>
            <!-- Filters -->
            <div class="-mx-4 mt-4 overflow-x-auto px-4 [scrollbar-width:none]">
                <div class="flex w-max gap-2" role="group" aria-label="দূরত্ব">
                    <ChoiceChip
                        v-for="r in radiusOptions"
                        :key="r.value"
                        :model-value="feed.radius"
                        :value="r.value"
                        name="radius"
                        @update:model-value="setRadius"
                    >{{ r.label }}</ChoiceChip>
                </div>
            </div>
            <div class="-mx-4 mt-2 overflow-x-auto px-4 [scrollbar-width:none]">
                <div class="flex w-max gap-2" role="group" aria-label="বেলা">
                    <ChoiceChip
                        v-for="m in mealOptions"
                        :key="m.value"
                        :model-value="feed.meal ?? ''"
                        :value="m.value"
                        name="meal"
                        @update:model-value="setMeal"
                    >{{ m.label }}</ChoiceChip>
                </div>
            </div>

            <p v-if="error" class="error-text mt-6" role="alert">{{ error }}</p>

            <div v-if="loading && !posts.length" class="mt-6 space-y-4" aria-busy="true" aria-label="লোড হচ্ছে">
                <div v-for="i in 2" :key="i" class="h-80 animate-pulse rounded-[var(--radius-plate)] bg-plate"></div>
            </div>

            <div v-else-if="!posts.length && !error" class="mt-6 rounded-[var(--radius-plate)] border border-dashed border-rule bg-plate p-6">
                <p class="font-semibold">এই দূরত্বে এখন কোনো খাবার নেই।</p>
                <p class="mt-1 text-muted">একটু বড় এলাকায় দেখুন, বা কিছুক্ষণ পরে আবার আসুন।</p>
                <button v-if="widerRadius" type="button" class="btn-quiet -ml-4 mt-2" @click="setRadius(widerRadius.value)">
                    {{ widerRadius.label }} পর্যন্ত দেখুন
                </button>
            </div>

            <ul v-else class="mt-5 space-y-5">
                <li v-for="post in posts" :key="post.id">
                    <PostCard :post="post" :kitchen-name="post.kitchen.name">
                        <template #meta>
                            <span class="font-semibold text-ink">{{ distanceText(post.distance_m) }}</span>
                            · হেঁটে {{ bn(post.walk_minutes) }} মিনিট · {{ post.kitchen.name }} · {{ agoText(post.published_at) }}
                        </template>
                        <template #actions>
                            <ContactButtons :post="post" @gone="dropPost" />
                        </template>
                    </PostCard>
                </li>
            </ul>

            <button v-if="nextPage" type="button" class="btn-quiet mt-6 w-full border border-rule" :disabled="loading" @click="load(nextPage)">
                {{ loading ? 'লোড হচ্ছে…' : 'আরও দেখুন' }}
            </button>

            <p v-if="loadedAt && posts.length" class="mt-6 text-center text-sm text-muted">
                {{ clockText(loadedAt) }}-এর হালনাগাদ ·
                <button type="button" class="text-leaf underline-offset-2 hover:underline" @click="load(1)">আবার দেখুন</button>
            </p>
        </template>

        <aside class="mt-12 border-t border-rule pt-6">
            <p class="font-semibold">প্রতিদিন ঘরের খাবার চান?</p>
            <p class="mt-1 text-muted">পাড়ার একজন গৃহিণী রোজ রান্না করবেন, শাহিদার বাসায় পৌঁছে দেবেন।</p>
            <RouterLink to="/regular" class="btn-quiet -ml-4 mt-1">নিয়মিত খাবারের অনুরোধ করুন</RouterLink>
        </aside>
        <aside class="mt-6 border-t border-rule pt-6">
            <p class="font-semibold">বাসায় রান্না করেন?</p>
            <p class="mt-1 text-muted">পরিবারের রান্না থেকে এক-দুই প্লেট পাড়ার মানুষের কাছে বিক্রি করুন।</p>
            <RouterLink to="/cook" class="btn-quiet -ml-4 mt-1">দাওয়াতে রাঁধুনি হিসেবে যোগ দিন</RouterLink>
        </aside>

        <!-- F31: plain links (full page loads) to the public pages search engines index. -->
        <footer class="mt-10 border-t border-rule pt-6 text-sm text-muted">
            <template v-if="areas.length">
                <p class="font-semibold text-ink">যেসব এলাকায় দাওয়াত আছে</p>
                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                    <li v-for="a in areas" :key="a.slug">
                        <a :href="`/area/${a.slug}`" class="text-leaf underline-offset-2 hover:underline">{{ a.name }}</a>
                    </li>
                </ul>
            </template>
            <ul class="mt-5 flex flex-wrap gap-x-4 gap-y-1">
                <li><a href="/lunch" class="text-leaf underline-offset-2 hover:underline">প্রতিদিনের লাঞ্চ</a></li>
                <li><a href="/rannaghor" class="text-leaf underline-offset-2 hover:underline">গৃহিণীদের জন্য</a></li>
                <li><a href="/about" class="text-leaf underline-offset-2 hover:underline">দাওয়াত সম্পর্কে</a></li>
                <li><a :href="CONTACT.facebook" class="text-leaf underline-offset-2 hover:underline" rel="noopener">ফেসবুক গ্রুপ</a></li>
            </ul>
            <!-- F33: the same contact as the join popup, for anyone who closed it. -->
            <p class="mt-5">
                যোগ দিতে বা জানতে: <a :href="`tel:${CONTACT.phoneIntl}`" class="font-semibold text-ink">{{ bn(CONTACT.phone) }}</a>
                · <a :href="CONTACT.whatsapp" target="_blank" rel="noopener" class="text-leaf underline-offset-2 hover:underline">হোয়াটসঅ্যাপ</a>
            </p>
        </footer>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChoiceChip from '@/components/ChoiceChip.vue';
import ContactButtons from '@/components/ContactButtons.vue';
import PostCard from '@/components/PostCard.vue';
import { api, errorMessage } from '@/lib/api';
import { bn, placeOf } from '@/lib/bn';
import { CONTACT } from '@/lib/contact';
import { agoText, clockText, distanceText } from '@/lib/format';
import { currentPosition, useFeedStore } from '@/stores/feed';

const feed = useFeedStore();
const route = useRoute();
const router = useRouter();

const radiusOptions = [
    { value: 'walk', label: '৫ মিনিট হাঁটা' },
    { value: '1km', label: '১ কিমি' },
    { value: '3km', label: '৩ কিমি' },
    { value: 'all', label: 'পুরো এলাকা' },
];
const mealOptions = [
    { value: '', label: 'সব বেলা' },
    { value: 'breakfast', label: 'সকালের নাস্তা' },
    { value: 'lunch', label: 'দুপুর' },
    { value: 'snack', label: 'বিকেলের নাস্তা' },
    { value: 'dinner', label: 'রাত' },
];

const areas = ref([]);
const posts = ref([]);
const nextPage = ref(null);
const loading = ref(false);
const error = ref('');
const loadedAt = ref(null);
const choosing = ref(false);
const locating = ref(false);
const locateError = ref('');
const areaName = ref('');

const placeLabel = computed(() => {
    if (feed.mode === 'location') return 'আপনার আশেপাশে, কাছেরটা আগে';
    if (feed.mode === 'area') return `${placeOf(areaName.value || 'এলাকা')} আশেপাশে, কাছেরটা আগে`;
    return 'আশেপাশের গৃহিণীদের আজকের রান্না';
});

const widerRadius = computed(() => {
    const i = radiusOptions.findIndex((r) => r.value === feed.radius);
    return radiusOptions[i + 1] ?? null;
});

async function load(page = 1) {
    const params = feed.params();
    if (!params) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/feed', { params: { ...params, page } });
        posts.value = page === 1 ? data.data : [...posts.value, ...data.data];
        nextPage.value = data.meta.current_page < data.meta.last_page ? data.meta.current_page + 1 : null;
        areaName.value = data.meta.area?.name ?? areaName.value;
        loadedAt.value = new Date().toISOString();
    } catch (e) {
        error.value = errorMessage(e);
        if (e.response?.status === 422) {
            // The saved area was switched off, for example.
            feed.reset();
        }
    } finally {
        loading.value = false;
    }
}

async function useMyLocation() {
    locateError.value = '';
    locating.value = true;
    try {
        feed.usePosition(await currentPosition());
        choosing.value = false;
        await load(1);
    } catch {
        locateError.value = 'লোকেশন পাওয়া যায়নি। নিচে থেকে এলাকা বেছে নিন।';
    } finally {
        locating.value = false;
    }
}

async function pickArea(slug) {
    if (!slug) return;
    feed.useArea(slug);
    areaName.value = areas.value.find((a) => a.slug === slug)?.name ?? '';
    choosing.value = false;
    await load(1);
}

function changePlace() {
    choosing.value = true;
}

/** A post sold out or expired since the feed loaded. */
function dropPost(id) {
    setTimeout(() => (posts.value = posts.value.filter((p) => p.id !== id)), 2500);
}

function setRadius(value) {
    feed.setRadius(value);
    load(1);
}

function setMeal(value) {
    feed.meal = value || null;
    load(1);
}

// Food changes quickly around lunch: refresh when the buyer comes back to the tab.
function onVisible() {
    if (document.visibilityState === 'visible' && loadedAt.value && Date.now() - new Date(loadedAt.value) > 2 * 60 * 1000) {
        load(1);
    }
}

onMounted(async () => {
    document.addEventListener('visibilitychange', onVisible);
    try {
        const { data } = await api.get('/areas');
        areas.value = data.data;
        areaName.value = areas.value.find((a) => a.slug === feed.areaSlug)?.name ?? '';
    } catch {
        // The area list is only needed for the chooser.
    }

    // F31: an area page links here as /?area=uttar-badda.
    const wanted = route.query.area;
    if (typeof wanted === 'string') {
        router.replace({ query: {} });
        if (areas.value.some((a) => a.slug === wanted)) {
            await pickArea(wanted);
            return;
        }
    }

    if (feed.mode === 'location') {
        try {
            feed.usePosition(await currentPosition());
        } catch {
            feed.reset();
            return;
        }
    }
    await load(1);
});

onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible));
</script>
