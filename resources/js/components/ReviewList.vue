<template>
    <!-- F47: reviews for the cook / shahidar, with a reply box. -->
    <div>
        <p v-if="!reviews.length" class="text-sm text-muted">এখনো কোনো রিভিউ নেই।</p>
        <div v-for="r in reviews" :key="r.id" class="mt-3 border-t border-rule pt-3 text-sm first:mt-0 first:border-0 first:pt-0">
            <p><span class="text-turmeric">{{ '★'.repeat(r.stars) }}</span><span class="text-rule">{{ '★'.repeat(5 - r.stars) }}</span> · {{ r.who }}</p>
            <p class="text-xs text-muted">{{ r.meal_slot_label }} · {{ r.title }} · {{ dateText(r.date) }}</p>
            <p v-if="r.body" class="mt-1">“{{ r.body }}”</p>
            <p v-if="r.reply" class="mt-1 rounded-lg bg-leaf-soft px-3 py-2">
                <span class="font-semibold">{{ r.reply_label }}:</span> {{ r.reply }}<span v-if="r.reply_hidden" class="text-xs text-muted"> (দাওয়াত লুকিয়ে রেখেছে)</span>
            </p>
            <template v-if="replying === r.id">
                <textarea v-model="text" class="field mt-2 text-base" rows="2" maxlength="500" placeholder="গ্রাহককে উত্তর লিখুন"></textarea>
                <p v-if="error" class="error-text" role="alert">{{ error }}</p>
                <div class="mt-1 flex gap-2">
                    <button type="button" class="btn-primary w-auto px-4" :disabled="busy || text.trim().length < 2" @click="send(r)">পাঠান</button>
                    <button type="button" class="btn-quiet w-auto" @click="replying = null">থাক</button>
                </div>
            </template>
            <button v-else type="button" class="mt-1 font-semibold text-leaf" @click="start(r)">{{ r.reply ? 'উত্তর বদলান' : 'উত্তর দিন' }}</button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { api, errorMessage } from '@/lib/api';

const props = defineProps({
    reviews: { type: Array, default: () => [] },
    replyPath: { type: Function, required: true },
});
const emit = defineEmits(['replied']);
const replying = ref(null);
const text = ref('');
const busy = ref(false);
const error = ref('');

function dateText(d) {
    return d ? new Intl.DateTimeFormat('bn-BD', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${d}T00:00:00Z`)) : '';
}

function start(r) {
    replying.value = r.id;
    text.value = r.reply ?? '';
    error.value = '';
}

async function send(r) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(props.replyPath(r), { reply: text.value });
        replying.value = null;
        emit('replied', data.message);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
