<template>
    <!-- F45: where today's box is: booked → cook ready → on the way → delivered. -->
    <ol v-if="!stopped" class="mt-2 flex items-start gap-1 text-[11px] leading-tight" aria-label="বক্সের অবস্থা">
        <li v-for="(s, i) in steps" :key="s.label" class="flex flex-1 flex-col items-center text-center">
            <span class="flex w-full items-center">
                <span class="h-0.5 flex-1" :class="i === 0 ? 'opacity-0' : s.done ? 'bg-leaf' : 'bg-rule'"></span>
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full text-[10px]" :class="s.done ? 'bg-leaf text-white' : 'border border-rule bg-plate text-muted'">{{ s.done ? '✓' : bn(i + 1) }}</span>
                <span class="h-0.5 flex-1" :class="i === steps.length - 1 ? 'opacity-0' : steps[i + 1].done ? 'bg-leaf' : 'bg-rule'"></span>
            </span>
            <span class="mt-1" :class="s.done ? 'font-semibold text-ink' : 'text-muted'">{{ s.label }}</span>
            <span v-if="s.at" class="text-muted">{{ clockText(s.at) }}</span>
        </li>
    </ol>
</template>

<script setup>
import { computed } from 'vue';
import { bn } from '@/lib/bn';
import { clockText } from '@/lib/format';

const props = defineProps({ order: { type: Object, required: true } });
const stopped = computed(() => ['cancelled', 'asking', 'failed'].includes(props.order.status));
const steps = computed(() => {
    const o = props.order;
    const delivered = o.status === 'delivered';
    const picked = delivered || o.status === 'picked_up';
    return [
        { label: 'বুক হয়েছে', done: true, at: null },
        { label: 'রান্না রেডি', done: Boolean(o.ready_at) || picked, at: o.ready_at },
        { label: 'পথে', done: picked, at: o.picked_at },
        { label: 'পৌঁছেছে', done: delivered, at: o.delivered_at },
    ];
});
</script>
