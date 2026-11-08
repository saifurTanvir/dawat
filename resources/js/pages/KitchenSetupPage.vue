<template>
    <section class="pt-4" aria-labelledby="setup-title">
        <RouterLink to="/cook" class="text-sm text-leaf">← আমার রান্নাঘর</RouterLink>
        <h1 id="setup-title" class="mt-2 font-serif text-2xl font-bold">রান্নাঘর সেটআপ</h1>
        <p class="mt-1 text-muted">একবার ঠিক করলেই হবে। পরে যেকোনো সময় বদলাতে পারবেন।</p>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>

        <form v-else class="mt-6 space-y-8" novalidate @submit.prevent="save">
            <fieldset>
                <legend class="font-semibold">বাসার লোকেশন</legend>
                <p class="mt-1 text-sm text-muted">
                    ঠিকানা কেউ দেখবে না। ক্রেতারা শুধু দেখবেন খাবারটা কত দূরে, যেমন “৪০০ মি”।
                </p>

                <button type="button" class="btn-quiet -ml-4 mt-2" :disabled="locating" @click="useMyLocation">
                    {{ locating ? 'লোকেশন খোঁজা হচ্ছে…' : 'আমি এখন বাসায় আছি — এই লোকেশন নিন' }}
                </button>
                <p v-if="locateError" class="error-text" role="alert">{{ locateError }}</p>

                <p v-if="hasLocation && !point" class="mt-2 rounded-lg bg-leaf-soft p-3 text-sm">
                    লোকেশন আগেই দেওয়া আছে। বাসা বদলালে শুধু তখন নতুন করে পিন দিন।
                </p>
                <MapPicker v-model="point" :fallback-center="mapFallback" class="mt-3" />
                <p v-if="fieldError('lat')" class="error-text" role="alert">{{ fieldError('lat') }}</p>
            </fieldset>

            <fieldset>
                <legend class="font-semibold">এলাকা</legend>
                <p class="mt-1 text-sm text-muted">ক্রেতারা এলাকা বেছে খাবার খোঁজেন। পিন দিলে কাছের এলাকা নিজে থেকে বাছাই হবে।</p>
                <select v-model="areaId" class="field mt-2" aria-label="এলাকা">
                    <option :value="null" disabled>এলাকা বেছে নিন</option>
                    <option v-for="area in areas" :key="area.id" :value="area.id">
                        {{ area.name }}<template v-if="area.distance_m != null"> ({{ distanceText(area.distance_m) }})</template>
                    </option>
                </select>
                <p v-if="fieldError('area_id')" class="error-text" role="alert">{{ fieldError('area_id') }}</p>
            </fieldset>

            <fieldset>
                <legend class="font-semibold">ক্রেতা কীভাবে যোগাযোগ করবেন</legend>
                <p class="mt-1 text-sm text-muted">পোস্টের নিচে শুধু এই বাটনগুলো দেখাবে।</p>
                <div class="mt-3 divide-y divide-rule rounded-[var(--radius-plate)] bg-plate">
                    <label v-for="opt in contactOptions" :key="opt.key" class="flex items-start gap-3 p-4">
                        <input v-model="contact[opt.key]" type="checkbox" class="mt-1.5 size-5 accent-leaf" />
                        <span>
                            <span class="block font-semibold">{{ opt.label }}</span>
                            <span class="block text-sm text-muted">{{ opt.help }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="fieldError('allow_comment')" class="error-text" role="alert">{{ fieldError('allow_comment') }}</p>

                <ContactNumberCard v-if="kitchen" :kitchen="kitchen" class="mt-6" @updated="kitchen = $event" />
            </fieldset>

            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
            <button type="submit" class="btn-primary" :disabled="busy || !(point || hasLocation) || !areaId">
                {{ busy ? 'সংরক্ষণ হচ্ছে…' : 'সংরক্ষণ করুন' }}
            </button>
        </form>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import ContactNumberCard from '@/components/ContactNumberCard.vue';
import MapPicker from '@/components/MapPicker.vue';
import { api, errorMessage } from '@/lib/api';
import { distanceText } from '@/lib/format';

const router = useRouter();

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const errors = ref({});
const areas = ref([]);
const areaId = ref(null);
const point = ref(null);
const hasLocation = ref(false);
const locating = ref(false);
const locateError = ref('');
const kitchen = ref(null);
const contact = ref({ allow_order: true, allow_call: false, allow_message: true, allow_comment: true });

const contactOptions = [
    { key: 'allow_order', label: '"নিতে চাই" বাটন', help: 'ক্রেতা অ্যাপেই প্লেট বুক করবেন। শাহিদার পৌঁছে দেবেন, বা ক্রেতা নিজে নেবেন।' },
    { key: 'allow_message', label: 'হোয়াটসঅ্যাপে মেসেজ', help: 'ক্রেতা আগে থেকে লেখা একটা মেসেজ পাঠাবেন।' },
    { key: 'allow_comment', label: 'পোস্টে কমেন্ট', help: 'সবাই দেখতে পাবেন। নম্বর দেখা যায় না।' },
    { key: 'allow_call', label: 'ফোন কল', help: 'ক্রেতা সরাসরি কল করবেন। নম্বর তাঁর ফোনে থেকে যাবে।' },
];

const mapFallback = computed(() => areas.value.find((a) => a.id === areaId.value)?.center ?? undefined);

function fieldError(key) {
    return errors.value[key]?.[0] ?? '';
}

async function loadAreas(near) {
    const { data } = await api.get('/areas', { params: near ? { lat: near.lat, lng: near.lng } : {} });
    areas.value = data.data;
}

onMounted(async () => {
    try {
        const [{ data: loaded }] = await Promise.all([api.get('/kitchen'), loadAreas()]);
        areaId.value = loaded.data.area?.id ?? null;
        // The server never sends saved coordinates back; the cook re-pins only to move house.
        hasLocation.value = loaded.data.has_location;
        const k = loaded.data;
        contact.value = { allow_order: k.allow_order, allow_call: k.allow_call, allow_message: k.allow_message, allow_comment: k.allow_comment };
        kitchen.value = k;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

// When a pin is placed, re-sort areas by distance and pre-select the nearest.
watch(point, async (value) => {
    if (!value) return;
    try {
        await loadAreas(value);
        if (!areaId.value && areas.value.length) {
            areaId.value = areas.value[0].id;
        }
    } catch {
        // Keep the old list; not worth bothering the cook.
    }
});

function useMyLocation() {
    locateError.value = '';
    if (!('geolocation' in navigator)) {
        locateError.value = 'এই ফোনে লোকেশন পাওয়া যাচ্ছে না। ম্যাপে নিজে পিন দিন।';
        return;
    }
    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            point.value = { lat: Number(pos.coords.latitude.toFixed(6)), lng: Number(pos.coords.longitude.toFixed(6)) };
            locating.value = false;
        },
        () => {
            locateError.value = 'লোকেশনের অনুমতি পাওয়া যায়নি। ম্যাপে নিজে পিন দিন।';
            locating.value = false;
        },
        { enableHighAccuracy: true, timeout: 15000 },
    );
}

async function save() {
    error.value = '';
    errors.value = {};
    busy.value = true;
    try {
        await api.patch('/kitchen', { area_id: areaId.value, ...(point.value ?? {}), ...contact.value });
        router.push({ name: 'cook' });
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        error.value = Object.keys(errors.value).length ? '' : errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
