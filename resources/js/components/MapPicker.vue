<template>
    <div>
        <!-- F62: find the place by an address or a map link, or use where the phone is now. -->
        <div v-if="search || locate" class="mb-3 space-y-2">
            <form v-if="search" class="flex gap-2" @submit.prevent="find">
                <label class="sr-only" for="map-search">ঠিকানা বা গুগল ম্যাপের লিংক</label>
                <input id="map-search" v-model.trim="query" class="field min-w-0 flex-1 text-base" type="text" placeholder="ঠিকানা বা গুগল ম্যাপের লিংক" />
                <button type="submit" class="btn-quiet shrink-0 border border-rule px-3" :disabled="finding || query.length < 3">{{ finding ? 'খুঁজছি…' : 'খুঁজুন' }}</button>
            </form>
            <ul v-if="results.length > 1" class="divide-y divide-rule rounded-xl border border-rule bg-plate text-sm">
                <li v-for="r in results" :key="`${r.lat},${r.lng}`">
                    <button type="button" class="block w-full px-3 py-2 text-left" @click="pick(r)">{{ r.label }}</button>
                </li>
            </ul>
            <button v-if="locate" type="button" class="btn-quiet -ml-4" :disabled="locating" @click="here">
                {{ locating ? 'লোকেশন খোঁজা হচ্ছে… কয়েক সেকেন্ড লাগবে' : '📍 আমি এখন বাসায় আছি — এই লোকেশন নিন' }}
            </button>
            <p v-if="findMessage" class="text-sm text-chili" role="status">{{ findMessage }}</p>
        </div>

        <!-- F112: satellite photo by default, so a cook can see her own roof. -->
        <div class="mb-2 flex gap-2" role="group" aria-label="ম্যাপের ধরন">
            <button type="button" :class="['chip-toggle', layer === 'satellite' && 'is-on']" :aria-pressed="layer === 'satellite'" @click="setLayer('satellite')">🛰️ স্যাটেলাইট</button>
            <button type="button" :class="['chip-toggle', layer === 'streets' && 'is-on']" :aria-pressed="layer === 'streets'" @click="setLayer('streets')">🗺️ রাস্তা</button>
        </div>

        <div class="relative">
            <div
                ref="mapEl"
                class="h-[60vh] max-h-[32rem] min-h-72 w-full overflow-hidden rounded-[var(--radius-plate)] border border-rule bg-leaf-soft"
                role="application"
                aria-label="ম্যাপ: ম্যাপ সরিয়ে মাঝের পিনটা বাসার ওপর আনুন"
            ></div>
            <!-- F112: the pin stays in the middle; the map moves under it. -->
            <span class="dawat-pin dawat-pin-center" :class="{ 'is-unset': !modelValue }" aria-hidden="true"></span>
        </div>

        <p v-if="accuracyText" class="mt-2 rounded-lg bg-leaf-soft p-3 text-sm" role="status">{{ accuracyText }}</p>
        <p class="mt-2 text-sm text-muted">
            ম্যাপটা আঙুল দিয়ে সরান — মাঝখানের পিনটা ঠিক আপনার বাড়ির ছাদের ওপর আনুন। ভালো করে দেখতে ম্যাপ বড় (জুম) করুন।
        </p>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '@/lib/api';
import { bn } from '@/lib/bn';
import { locateBest } from '@/lib/locate';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    /** { lat, lng } or null */
    modelValue: { type: Object, default: null },
    /** Where to look when there is no pin yet. */
    fallbackCenter: { type: Object, default: () => ({ lat: 23.7808, lng: 90.3792 }) },
    /** F62: an address / map-link search box above the map. */
    search: { type: Boolean, default: true },
    /** F62: a "where I am now" button. */
    locate: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue']);

const mapEl = ref(null);

// F62
const query = ref('');
const results = ref([]);
const finding = ref(false);
const locating = ref(false);
const findMessage = ref('');

// F112
const layer = ref('satellite');
const accuracy = ref(null);
const accuracyText = computed(() => {
    if (accuracy.value == null) return '';
    const m = bn(accuracy.value);
    if (accuracy.value > 100) {
        return `ফোনের লোকেশন পুরো ঠিক আসেনি (প্রায় ${m} মিটার এদিক-ওদিক)। বারান্দায় বা জানালার কাছে গিয়ে আবার চাপুন, অথবা স্যাটেলাইট ম্যাপে নিজের বাড়ির ছাদ খুঁজে পিনটা তার ওপর আনুন।`;
    }
    return `ফোন বলছে আপনি প্রায় ${m} মিটারের মধ্যে আছেন (নীল বৃত্ত)। বৃত্তের ভেতরে নিজের বাড়ির ছাদ খুঁজে পিনটা ঠিক তার ওপর আনুন।`;
});

let map = null;
let circle = null;
let expected = null; // where the code itself last moved the map
const layers = {};

function pick(r) {
    results.value = [];
    clearAccuracy();
    moveTo([r.lat, r.lng]);
    emitPoint({ lat: r.lat, lng: r.lng });
}

async function find() {
    finding.value = true;
    findMessage.value = '';
    results.value = [];
    try {
        const { data } = await api.get('/geocode', { params: { q: query.value } });
        results.value = data.data;
        if (data.data.length === 1) pick(data.data[0]);
        if (!data.data.length) findMessage.value = data.message || 'খুঁজে পাওয়া যায়নি। ম্যাপ সরিয়ে নিজের বাড়ি খুঁজুন।';
    } catch {
        findMessage.value = 'এখন খোঁজা যাচ্ছে না। ম্যাপ সরিয়ে পিনটা বাড়ির ওপর আনুন।';
    } finally {
        finding.value = false;
    }
}

async function here() {
    findMessage.value = '';
    locating.value = true;
    try {
        const best = await locateBest();
        moveTo([best.lat, best.lng], best.accuracy > 100 ? 17 : 19);
        emitPoint(best);
        showAccuracy(best);
    } catch (e) {
        findMessage.value =
            e?.message === 'unsupported'
                ? 'এই ফোনে লোকেশন নেওয়া যায় না। ম্যাপ সরিয়ে পিনটা বাড়ির ওপর আনুন।'
                : 'লোকেশন পাওয়া যায়নি। ফোনের লোকেশন চালু করে অনুমতি দিন, বা ম্যাপ সরিয়ে নিজে বাড়ি খুঁজুন।';
    } finally {
        locating.value = false;
    }
}

function showAccuracy({ lat, lng, accuracy: metres }) {
    accuracy.value = metres;
    circle?.remove();
    circle = L.circle([lat, lng], { radius: metres, color: '#2563eb', weight: 1, fillOpacity: 0.12, interactive: false }).addTo(map);
}

function clearAccuracy() {
    accuracy.value = null;
    circle?.remove();
    circle = null;
}

function moveTo(latlng, zoom = null) {
    expected = L.latLng(latlng);
    map.setView(latlng, zoom ?? Math.max(map.getZoom(), 18));
}

function emitPoint({ lat, lng }) {
    emit('update:modelValue', { lat: Number(Number(lat).toFixed(6)), lng: Number(Number(lng).toFixed(6)) });
}

function setLayer(name) {
    layer.value = name;
    if (!map) return;
    for (const [key, group] of Object.entries(layers)) {
        if (key === name) group.addTo(map);
        else group.remove();
    }
}

onMounted(() => {
    const start = props.modelValue ?? props.fallbackCenter;
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: true }).setView(
        [start.lat, start.lng],
        props.modelValue ? 18 : 15,
    );

    layers.satellite = L.layerGroup([
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 19,
            maxZoom: 20,
            attribution: 'ছবি &copy; Esri, Maxar',
        }),
        // Road lines and place names over the photo.
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 19,
            maxZoom: 20,
        }),
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 19,
            maxZoom: 20,
        }),
    ]);
    layers.streets = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxNativeZoom: 19,
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap',
    });
    setLayer(layer.value);

    // The pin is the middle of the map: when the person moves the map, that is the new point.
    let lastCenter = map.getCenter();
    map.on('moveend', () => {
        const center = map.getCenter();
        const moved = !center.equals(lastCenter);
        lastCenter = center;
        if (expected && center.distanceTo(expected) < 0.5) {
            expected = null; // the code moved it; the point is already known
            return;
        }
        expected = null;
        // Only zoomed, no pin yet: the area's middle is not her house.
        if (!moved && !props.modelValue) return;
        emitPoint(center);
    });
    map.on('click', (e) => map.panTo(e.latlng));
});

watch(
    () => props.modelValue,
    (value) => {
        if (!map || !value) return;
        const c = map.getCenter();
        if (Math.abs(c.lat - value.lat) > 1e-6 || Math.abs(c.lng - value.lng) > 1e-6) {
            moveTo([value.lat, value.lng]);
        }
    },
);

watch(
    () => props.fallbackCenter,
    (center) => {
        if (map && !props.modelValue && center) {
            expected = L.latLng(center.lat, center.lng);
            map.setView([center.lat, center.lng], 15);
        }
    },
);

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    circle = null;
});
</script>

<style>
.dawat-pin {
    position: absolute;
    left: 2px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50% 50% 50% 0;
    background: var(--color-leaf);
    border: 3px solid #fff;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.35);
    transform: rotate(-45deg);
}
/* F112: fixed in the middle of the map, tip on the centre point. */
.dawat-pin-center {
    left: 50%;
    top: 50%;
    z-index: 500;
    margin-left: -12px;
    margin-top: -32px;
    pointer-events: none;
}
.dawat-pin-center.is-unset {
    opacity: 0.55;
}
.chip-toggle {
    border: 1px solid var(--color-rule);
    border-radius: 999px;
    padding: 0.35rem 0.85rem;
    font-size: 0.875rem;
    font-weight: 600;
    background: var(--color-plate);
}
.chip-toggle.is-on {
    background: var(--color-leaf);
    border-color: var(--color-leaf);
    color: #fff;
}
</style>
