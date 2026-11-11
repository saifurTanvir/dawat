<template>
    <div class="flex gap-1" role="radiogroup" aria-label="কয় তারা">
        <button
            v-for="n in 5"
            :key="n"
            type="button"
            role="radio"
            :aria-checked="modelValue === n"
            :aria-label="`${bn(n)} তারা`"
            class="text-3xl leading-none"
            :class="n <= modelValue ? 'text-turmeric' : 'text-rule'"
            @click="$emit('update:modelValue', n)"
        >★</button>
    </div>
</template>

<script setup>
import { bn } from '@/lib/bn';

defineProps({ modelValue: { type: Number, default: 0 } });
defineEmits(['update:modelValue']);
</script>
