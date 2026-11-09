<template>
    <!-- F35: shows buyers where the feed is looking from and how far it reaches. -->
    <div class="mt-3 rounded-xl bg-plate px-4 py-3">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm leading-relaxed">
                <span aria-hidden="true">📍</span>
                <template v-if="mode === 'location'">
                    আপনি আছেন <strong>{{ nearArea ? `${placeOf(nearArea)} কাছে` : 'আপনার লোকেশনে' }}</strong>
                </template>
                <template v-else>
                    এলাকা: <strong>{{ nearArea || 'বাছাই করা এলাকা' }}</strong> <span class="text-muted">(এলাকার মাঝখান থেকে)</span>
                </template>
                <br />
                <span class="text-muted">{{ radiusText }} খাবার দেখাচ্ছে</span>
            </p>
            <button
                type="button"
                class="btn-quiet -mr-3 min-h-10 shrink-0 px-3 text-sm"
                :aria-expanded="open"
                @click="open = !open"
            >
                {{ open ? 'ম্যাপ লুকান' : 'ম্যাপে দেখুন' }}
            </button>
        </div>
        <div v-if="open" ref="mapEl" class="mt-3 h-52 w-full overflow-hidden rounded-lg border border-rule bg-leaf-soft" role="img" :aria-label="`ম্যাপ: ${radiusText} এলাকা`"></div>
        <p v-if="open" class="mt-2 text-xs text-muted">আপনার লোকেশন শুধু এই ফোনে থাকে, কোথাও জমা হয় না। সবুজ বৃত্তের ভেতরের রান্না দেখাচ্ছে।</p>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { bn, placeOf } from '@/lib/bn';

const props = defineProps({
    mode: { type: String, required: true }, // 'location' | 'area'
    nearArea: { type: String, default: '' },
    /** { lat, lng } of the buyer, or of the area's centre */
    center: { type: Object, default: null },
    radiusM: { type: Number, default: 3000 },
});

const open = ref(false);
const mapEl = ref(null);
let map = null;

const radiusText = computed(() => {
    const m = props.radiusM;
    return m < 1000 ? `${bn(m)} মিটারের মধ্যে` : `${bn(Math.round(m / 100) / 10)} কিমির মধ্যে`;
});

function draw() {
    if (!mapEl.value || !props.center) return;
    map?.remove();
    const c = [props.center.lat, props.center.lng];
    map = L.map(mapEl.value, { zoomControl: false, attributionControl: true, scrollWheelZoom: false }).setView(c, 13);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const circle = L.circle(c, { radius: props.radiusM, color: '#1e5b3f', weight: 2, fillColor: '#1e5b3f', fillOpacity: 0.08 }).addTo(map);
    L.circleMarker(c, { radius: 8, color: '#fff', weight: 3, fillColor: '#1e5b3f', fillOpacity: 1 }).addTo(map);
    map.fitBounds(circle.getBounds(), { padding: [12, 12] });
}

watch(open, async (v) => {
    if (v) {
        await nextTick();
        draw();
    } else {
        map?.remove();
        map = null;
    }
});
watch(() => [props.center?.lat, props.center?.lng, props.radiusM], () => open.value && draw());
onBeforeUnmount(() => map?.remove());
</script>
