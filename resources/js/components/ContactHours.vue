<template>
    <!-- F41: the person decides when Dawat and the shahidar may call them. -->
    <div v-if="contact" class="mt-4 rounded-[var(--radius-plate)] bg-plate p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="font-semibold"><span aria-hidden="true">📞</span> কখন ফোন করা যাবে</p>
                <p class="text-sm text-muted">{{ contact.label }} · {{ preferLabels[contact.prefer] }}</p>
            </div>
            <button v-if="!open" type="button" class="shrink-0 font-semibold text-leaf" @click="edit">বদলান</button>
        </div>

        <form v-if="open" class="mt-3 space-y-3" @submit.prevent="save">
            <div class="flex items-end gap-2">
                <label class="flex-1 text-sm">
                    <span class="mb-1 block text-muted">কখন থেকে</span>
                    <select v-model="from" class="field">
                        <option v-for="t in times" :key="t" :value="t">{{ clock(t) }}</option>
                    </select>
                </label>
                <span class="pb-3">–</span>
                <label class="flex-1 text-sm">
                    <span class="mb-1 block text-muted">কখন পর্যন্ত</span>
                    <select v-model="to" class="field">
                        <option v-for="t in times" :key="t" :value="t">{{ clock(t) }}</option>
                    </select>
                </label>
            </div>
            <fieldset class="text-sm">
                <legend class="mb-1 text-muted">কীভাবে যোগাযোগ পছন্দ</legend>
                <label v-for="(label, key) in preferLabels" :key="key" class="flex items-center gap-2 py-1">
                    <input v-model="prefer" type="radio" :value="key" class="size-5 accent-leaf" /> {{ label }}
                </label>
            </fieldset>
            <p class="rounded-lg bg-leaf-soft px-3 py-2 text-xs">
                দাওয়াতের ঠিক করা সময়: যেদিন আপনার বক্স বা রান্না আছে, সেই বেলার ডেলিভারির সময় শাহিদার দরকার হলে ফোন দিতে পারবেন —
                {{ contact.meal_windows.map((w) => w.label).join(' · ') }}।
            </p>
            <p v-if="error" class="error-text" role="alert">{{ error }}</p>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary w-auto px-6" :disabled="busy">{{ busy ? 'রাখা হচ্ছে…' : 'রাখুন' }}</button>
                <button type="button" class="btn-quiet w-auto" @click="open = false">থাক</button>
            </div>
        </form>
        <p v-if="saved" class="mt-2 text-sm text-leaf">রাখা হয়েছে।</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const contact = computed(() => auth.user?.contact ?? null);
const open = ref(false);
const busy = ref(false);
const saved = ref(false);
const error = ref('');
const from = ref(contact.value?.from ?? '09:00');
const to = ref(contact.value?.to ?? '21:00');
const prefer = ref(contact.value?.prefer ?? 'any');

const preferLabels = { any: 'ফোন বা হোয়াটসঅ্যাপ', call: 'ফোন কল', whatsapp: 'শুধু হোয়াটসঅ্যাপ মেসেজ' };

// Half-hour steps between the earliest and latest Dawat allows.
const times = computed(() => {
    const [eh] = (contact.value?.earliest ?? '07:00').split(':').map(Number);
    const [lh] = (contact.value?.latest ?? '22:00').split(':').map(Number);
    const out = [];
    for (let h = eh; h <= lh; h++) {
        out.push(`${String(h).padStart(2, '0')}:00`);
        if (h < lh) out.push(`${String(h).padStart(2, '0')}:30`);
    }
    return out;
});

function clock(hm) {
    const [h, m] = hm.split(':').map(Number);
    const part = h < 5 ? 'রাত' : h < 12 ? 'সকাল' : h < 15 ? 'দুপুর' : h < 18 ? 'বিকেল' : h < 19 ? 'সন্ধ্যা' : 'রাত';
    const h12 = h % 12 || 12;
    return `${part} ${bn(m ? `${h12}:${String(m).padStart(2, '0')}` : `${h12}টা`)}`;
}

function edit() {
    from.value = contact.value.from;
    to.value = contact.value.to;
    prefer.value = contact.value.prefer;
    saved.value = false;
    open.value = true;
}

async function save() {
    busy.value = true;
    error.value = '';
    saved.value = false;
    try {
        const { data } = await api.put('/me/contact-hours', { from: from.value, to: to.value, prefer: prefer.value });
        auth.user = data.data;
        open.value = false;
        saved.value = true;
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
