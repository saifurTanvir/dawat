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
                {{ locating ? 'লোকেশন খোঁজা হচ্ছে…' : '📍 আমি এখন বাসায় আছি — এই লোকেশন নিন' }}
            </button>
            <p v-if="findMessage" class="text-sm text-chili" role="status">{{ findMessage }}</p>
        </div>
        <div
            ref="mapEl"
            class="h-72 w-full overflow-hidden rounded-[var(--radius-plate)] border border-rule bg-leaf-soft"
            role="application"
            aria-label="ম্যাপ: বাসার জায়গায় পিন সরান"
        ></div>
        <p class="mt-2 text-sm text-muted">পিনটা টেনে বা ম্যাপে চাপ দিয়ে ঠিক বাসার জায়গায় বসান।</p>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '@/lib/api';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    /** { lat, lng } or null */
    modelValue: { type: Object, default: null },
    /** Where to look when there is no pin yet. */
    fallbackCenter: { type: Object, default: () => ({ lat: 23.7808, lng: 90.3792 }) },
    /** F62: an address / map-link search box above the map. */
    search: { type: Boolean, default: true },
    /** F62: a "where I am now" button (off where the page has its own). */
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

function pick(r) {
    results.value = [];
    place([r.lat, r.lng]);
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
        if (!data.data.length) findMessage.value = data.message || 'খুঁজে পাওয়া যায়নি।';
    } catch {
        findMessage.value = 'এখন খোঁজা যাচ্ছে না। ম্যাপে পিন সরিয়ে দিন।';
    } finally {
        finding.value = false;
    }
}

function here() {
    findMessage.value = '';
    if (!navigator.geolocation) {
        findMessage.value = 'এই ফোনে লোকেশন নেওয়া যায় না। ম্যাপে পিন সরান।';
        return;
    }
    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            locating.value = false;
            pick({ lat: pos.coords.latitude, lng: pos.coords.longitude });
        },
        () => {
            locating.value = false;
            findMessage.value = 'লোকেশন পাওয়া যায়নি। ফোনের লোকেশন চালু করে অনুমতি দিন, বা ঠিকানা খুঁজুন।';
        },
        { enableHighAccuracy: true, timeout: 15000 },
    );
}
let map = null;
let marker = null;

// A plain CSS pin, so no image assets are needed.
const pinIcon = L.divIcon({
    className: '',
    html: '<span class="dawat-pin" aria-hidden="true"></span>',
    iconSize: [28, 40],
    iconAnchor: [14, 38],
});

function place(latlng, { pan = true } = {}) {
    if (!marker) {
        marker = L.marker(latlng, { draggable: true, icon: pinIcon, keyboard: true, title: 'বাসা' }).addTo(map);
        marker.on('dragend', () => emitPoint(marker.getLatLng()));
    } else {
        marker.setLatLng(latlng);
    }
    if (pan) {
        map.setView(latlng, Math.max(map.getZoom(), 17));
    }
}

function emitPoint({ lat, lng }) {
    emit('update:modelValue', { lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)) });
}

onMounted(() => {
    const start = props.modelValue ?? props.fallbackCenter;
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: true }).setView(
        [start.lat, start.lng],
        props.modelValue ? 17 : 14,
    );
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (props.modelValue) {
        place([props.modelValue.lat, props.modelValue.lng], { pan: false });
    }

    map.on('click', (e) => {
        place(e.latlng, { pan: false });
        emitPoint(e.latlng);
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (map && value && (!marker || !marker.getLatLng().equals([value.lat, value.lng]))) {
            place([value.lat, value.lng]);
        }
    },
);

watch(
    () => props.fallbackCenter,
    (center) => {
        if (map && !props.modelValue && center) {
            map.setView([center.lat, center.lng], 15);
        }
    },
);

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
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
</style>
