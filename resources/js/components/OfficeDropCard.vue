<template>
    <!-- F68: one office's boxes for a meal. Pick up a cook's boxes together, hand the drop over once. -->
    <section class="mt-4 rounded-[var(--radius-plate)] border-2 bg-plate p-4" :class="drop.done ? 'border-rule' : 'border-turmeric'" :aria-label="`অফিস ড্রপ: ${drop.company}`">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-muted"><span aria-hidden="true">🏢</span> অফিস ড্রপ · {{ drop.meal_slot_label }}</p>
                <h2 class="text-lg font-semibold">{{ drop.company }}</h2>
                <p class="text-sm">{{ drop.address }}<template v-if="drop.area"> · {{ drop.area }}</template></p>
                <p v-if="drop.note" class="text-sm text-muted">{{ drop.note }}</p>
            </div>
        </div>
        <p class="mt-2 text-sm">
            মোট <strong>{{ bn(drop.boxes) }}টা বক্স</strong> ({{ bn(drop.plates) }} প্লেট) ·
            তুলতে {{ bn(drop.booked) }} · পথে {{ bn(drop.picked) }} · পৌঁছেছে {{ bn(drop.delivered) }}
        </p>
        <div v-if="drop.contact" class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-steel/50 px-3 py-2 text-sm">
            <span>বুঝে নেবেন: <strong>{{ drop.contact.name || drop.contact.phone }}</strong></span>
            <CallButtons :phone="drop.contact.phone" :contact="drop.contact.card" />
        </div>

        <!-- F95: go house by house on the map; each cook hears before he comes -->
        <RouterLink v-if="!inRound && !drop.done && (drop.booked || drop.picked)" :to="`/shahidar/round/${drop.id}`" class="btn-primary mt-3 flex items-center justify-center gap-2">
            <span aria-hidden="true">🗺️</span> {{ drop.booked ? 'রাউন্ড — ম্যাপ ধরে বাসায় বাসায়' : 'রাউন্ড — অফিসের পথ' }}
        </RouterLink>

        <!-- F110: evening — the empty boxes back to the cooks, and their money -->
        <RouterLink v-if="!inRound && drop.done" :to="`/shahidar/return/${drop.id}`" class="btn-primary mt-3 flex items-center justify-center gap-2">
            <span aria-hidden="true">🌙</span> ফেরত রাউন্ড — খালি বক্স আর গৃহিণীর টাকা
        </RouterLink>

        <!-- Pickup, cook by cook: one tap (and one bag sticker) for all her boxes -->
        <div v-for="k in drop.kitchens" :key="k.id" class="mt-3 border-t border-rule pt-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ k.name }}</p>
                    <p class="text-sm text-muted">{{ k.address }}</p>
                </div>
                <button v-if="k.booked" type="button" class="btn-primary min-h-10 w-auto shrink-0 px-4 text-sm" :disabled="busy" @click="startPickup(k)">
                    {{ bn(k.booked) }}টা পিকআপ
                </button>
            </div>
            <details class="mt-1 text-sm">
                <summary class="cursor-pointer text-muted">{{ bn(k.boxes.length) }}টা বক্স — কার কোনটা</summary>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="b in k.boxes" :key="b.id"><strong v-if="b.spot">{{ b.spot }} · </strong>বক্স {{ b.code }} · {{ b.name }} · {{ bn(b.plates) }} প্লেট · <span class="text-muted">{{ b.status_label }}</span></li>
                </ul>
            </details>
        </div>
        <p v-if="drop.bags.length" class="mt-2 text-xs text-muted">ব্যাগের স্টিকার: {{ drop.bags.join(', ') }}</p>

        <!-- Handover: once, with the contact person's code -->
        <p v-if="drop.done" class="mt-3 rounded-lg bg-leaf-soft px-3 py-2 text-sm">সব বুঝিয়ে দেওয়া হয়েছে<template v-if="drop.method_label"> · {{ drop.method_label }}</template></p>
        <template v-else-if="drop.picked">
            <p v-if="hasSpots" class="mt-3 rounded-lg bg-turmeric-soft px-3 py-2 text-sm">
                বক্সগুলো স্টিকারের "সারি · কলাম" মিলিয়ে সাজিয়ে রাখুন — সারি ১ সামনে, কলাম ১ বাঁ থেকে। বুঝিয়ে দিলে প্রত্যেক কর্মী নিজের সারি-কলাম নোটিফিকেশনে পাবেন।
            </p>
            <form v-if="handing" class="mt-3" @submit.prevent="deliver(code)">
                <label :for="`dc-${drop.id}`" class="mb-1 block text-sm font-semibold">{{ drop.contact?.name || 'দায়িত্বপ্রাপ্ত লোক' }}-এর SMS-এর ৪ অঙ্কের কোড</label>
                <div class="flex gap-2">
                    <input :id="`dc-${drop.id}`" v-model="code" class="field flex-1" inputmode="numeric" maxlength="4" autocomplete="one-time-code" />
                    <button type="submit" class="btn-primary w-auto px-4" :disabled="busy || code.length !== 4">বুঝিয়ে দিয়েছি</button>
                </div>
                <label v-if="drop.cash_paisa" class="mt-2 flex items-center gap-2 text-sm">
                    <input v-model="cash" type="checkbox" class="size-5 accent-leaf" />
                    {{ taka(drop.cash_paisa) }} নগদ পেয়েছি
                </label>
                <button type="button" class="mt-2 text-sm font-semibold text-leaf" :disabled="busy" @click="deliver('')">কোড ছাড়া বুঝিয়ে দিয়েছি</button>
                <p class="mt-1 text-xs text-muted">দায়িত্বপ্রাপ্ত লোক নিজে ব্যাগের QR স্ক্যান করেও বুঝে নিতে পারেন।</p>
            </form>
            <button v-else type="button" class="btn-primary mt-3" @click="openHand">অফিসে {{ bn(drop.picked) }}টা বক্স বুঝিয়ে দিচ্ছি</button>
        </template>
        <p v-if="error" class="mt-3 rounded-xl border-2 border-chili bg-chili/10 p-3 font-semibold text-chili" role="alert">{{ error }}</p>

        <StickerScanner v-if="picking" title="ব্যাগের স্টিকার স্ক্যান করুন" allow-skip @close="picking = null" @found="finishPickup" />
    </section>
</template>

<script setup>
import { ask } from '@/lib/ask';
import { computed, ref } from 'vue';
import CallButtons from '@/components/CallButtons.vue';
import StickerScanner from '@/components/StickerScanner.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const props = defineProps({ drop: { type: Object, required: true }, inRound: { type: Boolean, default: false } });
const emit = defineEmits(['updated', 'notice']);

const hasSpots = computed(() => props.drop.kitchens.some((k) => k.boxes.some((b) => b.spot)));
const busy = ref(false);
const error = ref('');
const picking = ref(null); // kitchen whose boxes are being picked up
const handing = ref(false);
const code = ref('');
const cash = ref(false); // nothing is marked paid unless he ticks it

async function post(path, body) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/shahidar/drops/${props.drop.id}/${path}`, body);
        emit('updated', data);
        return true;
    } catch (e) {
        error.value = errorMessage(e);
        if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
        return false;
    } finally {
        busy.value = false;
    }
}

function startPickup(k) {
    picking.value = k;
}

async function finishPickup(sticker) {
    const k = picking.value;
    picking.value = null;
    await post('pickup', { kitchen_id: k.id, sticker: sticker || null });
}

function openHand() {
    handing.value = true;
    code.value = '';
    cash.value = false;
}

async function deliver(c) {
    if (!c && !(await ask('কোড ছাড়া দিলে দাওয়াত টিম পরে যাচাই করবে। ঠিক আছে?', { danger: true }))) return;
    if (await post('deliver', { ...(c ? { code: c } : {}), cash_received: cash.value })) handing.value = false;
}
</script>
