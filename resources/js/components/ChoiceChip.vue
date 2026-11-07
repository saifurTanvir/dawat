<template>
    <label
        class="relative inline-flex min-h-11 cursor-pointer items-center rounded-full border px-4 select-none has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-turmeric"
        :class="checked ? 'border-leaf bg-leaf text-white' : 'border-rule bg-plate text-ink'"
    >
        <input
            :type="type"
            :name="name"
            class="sr-only"
            :checked="checked"
            @change="onChange"
        />
        <span><slot /></span>
    </label>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    value: { type: [String, Number, Boolean], required: true },
    type: { type: String, default: 'radio' },
    name: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);

const checked = computed(() => props.modelValue === props.value);

function onChange() {
    if (props.type === 'radio') {
        emit('update:modelValue', props.value);
    } else {
        emit('update:modelValue', !checked.value);
    }
}
</script>
