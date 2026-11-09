<template>
    <!-- F41: call only inside the person's hours; WhatsApp (free) always. -->
    <span class="flex shrink-0 flex-col items-end gap-0.5 text-right">
        <span class="flex items-center gap-3">
            <a v-if="canCall" :href="`tel:${phone}`" class="font-semibold text-leaf">ফোন</a>
            <a v-if="contact?.whatsapp_url" :href="contact.whatsapp_url" target="_blank" rel="noopener" class="font-semibold text-leaf">হোয়াটসঅ্যাপ</a>
        </span>
        <span v-if="contact && !contact.ok_now" class="text-xs text-chili">এখন কল নয় · {{ contact.hours }}</span>
        <span v-else-if="contact?.prefer === 'whatsapp'" class="text-xs text-muted">মেসেজ পছন্দ করেন</span>
        <span v-else-if="contact?.why" class="text-xs text-muted">{{ contact.why }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    phone: { type: String, required: true },
    contact: { type: Object, default: null },
});

// Old rows without contact info still get a call link.
const canCall = computed(() => !props.contact || (props.contact.ok_now && props.contact.prefer !== 'whatsapp'));
</script>
