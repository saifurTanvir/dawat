<template>
    <div>
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
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    /** { lat, lng } or null */
    modelValue: { type: Object, default: null },
    /** Where to look when there is no pin yet. */
    fallbackCenter: { type: Object, default: () => ({ lat: 23.7808, lng: 90.3792 }) },
});
const emit = defineEmits(['update:modelValue']);

const mapEl = ref(null);
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
