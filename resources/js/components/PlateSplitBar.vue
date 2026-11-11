<template>
    <!-- F44: where a post's plates went. Green regular customers, yellow being asked, blue feed orders, grey still on the feed. -->
    <div v-if="split && split.total">
        <div class="flex h-2 overflow-hidden rounded-full bg-rule" role="img" :aria-label="line">
            <span class="bg-leaf" :style="{ width: pct(split.regular) }"></span>
            <span class="bg-turmeric" :style="{ width: pct(split.asking) }"></span>
            <span class="bg-[#3B6EA8]" :style="{ width: pct(split.feed_orders) }"></span>
        </div>
        <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-muted">
            <span v-if="split.regular"><i class="mr-1 inline-block size-2 rounded-full bg-leaf"></i>নিয়মিত {{ bn(split.regular) }}</span>
            <span v-if="split.asking"><i class="mr-1 inline-block size-2 rounded-full bg-turmeric"></i>দাম জিজ্ঞেস {{ bn(split.asking) }}</span>
            <span v-if="split.feed_orders"><i class="mr-1 inline-block size-2 rounded-full bg-[#3B6EA8]"></i>ফিডে অর্ডার {{ bn(split.feed_orders) }}</span>
            <span><i class="mr-1 inline-block size-2 rounded-full bg-rule"></i>ফিডে বাকি {{ bn(split.left) }}</span>
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { bn } from '@/lib/bn';

const props = defineProps({ split: { type: Object, default: null } });
const pct = (n) => `${(100 * n) / props.split.total}%`;
const line = computed(() => props.split
    ? `নিয়মিত ${props.split.regular}, দাম জিজ্ঞেস ${props.split.asking}, ফিডে অর্ডার ${props.split.feed_orders}, ফিডে বাকি ${props.split.left}`
    : '');
</script>
