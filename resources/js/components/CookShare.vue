<template>
    <!-- F56: as the price is typed, what comes off and what the cook gets per plate. -->
    <div v-if="fees && share.price > 0" class="mt-2 rounded-lg bg-leaf-soft px-3 py-2 text-sm" role="status" aria-live="polite">
        <p>
            <span class="font-semibold">{{ who }} পাবেন {{ tk(share.cook) }}</span>
            <span class="text-muted"> / প্লেট</span>
        </p>
        <p class="text-xs text-muted">
            দাম {{ tk(share.price) }}
            <template v-if="share.delivery"> − ডেলিভারি খরচ {{ tk(share.delivery) }}</template>
            <template v-if="share.platform"> − দাওয়াতের ফি {{ tk(share.platform) }}</template>
        </p>
        <p v-if="share.platform || share.delivery" class="text-xs text-muted">ক্রেতা নিজে এসে নিলে পুরো {{ tk(share.price) }} {{ whose }}।</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { bn } from '@/lib/bn';
import { cookShare } from '@/lib/fees';

const props = defineProps({
    priceTaka: { type: [Number, String], default: 0 },
    fees: { type: Object, default: null },
    // The cook herself, or a shahidar/admin acting for her.
    forCook: { type: Boolean, default: true },
});

const share = computed(() => cookShare(props.priceTaka, props.fees));
const who = computed(() => (props.forCook ? 'আপনি' : 'গৃহিণী'));
const whose = computed(() => (props.forCook ? 'আপনার' : 'গৃহিণীর'));
const tk = (paisa) => `৳${bn(Number.isInteger(paisa / 100) ? paisa / 100 : (paisa / 100).toFixed(2))}`;
</script>
