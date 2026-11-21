<template>
    <!-- F64: guest plates my customers asked for. Cook first; if not, another post or another way. -->
    <section v-if="rows.length" class="mt-4 rounded-[var(--radius-plate)] bg-turmeric-soft p-4" aria-labelledby="guest-title">
        <h2 id="guest-title" class="font-semibold">অতিথির খাবার ({{ bn(rows.filter((g) => g.open).length) }}টা বাকি)</h2>
        <div v-for="g in rows" :key="g.id" class="mt-3 border-t border-rule pt-3 first:mt-2 first:border-0 first:pt-0">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ g.customer }} · {{ g.when }} · {{ bn(g.plates) }} প্লেট</p>
                    <p class="text-sm text-muted">{{ g.area }} · {{ g.address }}</p>
                    <p v-if="g.note" class="text-sm">“{{ g.note }}”</p>
                    <p class="mt-1 text-sm">
                        গৃহিণী {{ g.kitchen }}:
                        <strong :class="g.late || g.status === 'cook_no' ? 'text-chili' : ''">{{ g.late ? '৫ মিনিটে উত্তর দেননি — ফোন করুন' : g.status_label }}</strong>
                    </p>
                </div>
                <CallButtons :phone="g.phone" />
            </div>

            <p v-if="!g.open" class="mt-2 rounded-lg px-3 py-2 text-sm" :class="g.status === 'arranged' ? 'bg-leaf-soft' : 'bg-steel'">
                {{ g.status_label }}<template v-if="g.order_code"> · বক্স {{ g.order_code }}</template><template v-if="g.handled_note">: {{ g.handled_note }}</template>
            </p>
            <template v-else>
                <!-- Cook has not answered: call her, then say what she said -->
                <div v-if="g.status === 'asked'" class="mt-2 rounded-lg bg-plate p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold">গৃহিণীকে ফোন করুন</p>
                        <CallButtons v-if="g.cook_phone" :phone="g.cook_phone" :contact="g.cook_contact" />
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" class="min-h-10 rounded-xl bg-leaf px-2 text-sm font-semibold text-white" :disabled="busy" @click="act(g, 'cook', { yes: true })">উনি পারবেন</button>
                        <button type="button" class="min-h-10 rounded-xl border border-chili bg-plate px-2 text-sm font-semibold text-chili" :disabled="busy" @click="act(g, 'cook', { yes: false })">উনি পারবেন না</button>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-if="g.is_today" type="button" class="btn-quiet min-h-10 border border-rule px-3 text-sm" :disabled="busy" @click="loadPosts(g)">অন্য পোস্ট থেকে</button>
                    <button type="button" class="btn-quiet min-h-10 border border-rule px-3 text-sm" :disabled="busy" @click="noting = noting === g.id ? null : g.id">অন্যভাবে / হচ্ছে না</button>
                </div>

                <ul v-if="posts[g.id]" class="mt-2 space-y-2">
                    <li v-if="!posts[g.id].length" class="text-sm text-muted">এখন এত প্লেট বাকি আছে এমন কোনো পোস্ট নেই।</li>
                    <li v-for="p in posts[g.id]" :key="p.id" class="flex items-center justify-between gap-2 rounded-lg bg-plate p-3">
                        <span class="text-sm">
                            <span class="block font-semibold">{{ p.title }}</span>
                            {{ p.kitchen }}<template v-if="p.own_cook"> (নিজের গৃহিণী)</template> · {{ p.area }} · {{ taka(p.price_paisa) }} · বাকি {{ bn(p.plates_left) }}
                        </span>
                        <button type="button" class="btn-primary min-h-10 w-auto shrink-0 px-3 text-sm" :disabled="busy" @click="act(g, 'arrange', { post_id: p.id })">বুক করুন</button>
                    </li>
                </ul>

                <form v-if="noting === g.id" class="mt-2" @submit.prevent>
                    <input v-model.trim="notes[g.id]" class="field text-base" maxlength="300" placeholder="গ্রাহককে কী জানাবেন, যেমন: রাহেলা বিরিয়ানি দেবেন" aria-label="গ্রাহককে কী জানাবেন" />
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" class="btn-primary min-h-10 px-2 text-sm" :disabled="busy" @click="act(g, 'close', { note: notes[g.id] || null })">ব্যবস্থা হয়েছে</button>
                        <button type="button" class="min-h-10 rounded-xl border border-chili bg-plate px-2 text-sm font-semibold text-chili" :disabled="busy" @click="act(g, 'close', { note: notes[g.id] || null, cancel: true })">হচ্ছে না</button>
                    </div>
                </form>
            </template>
        </div>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import CallButtons from '@/components/CallButtons.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

defineProps({ rows: { type: Array, required: true } });
const emit = defineEmits(['updated']);

const busy = ref(false);
const error = ref('');
const posts = ref({});
const notes = ref({});
const noting = ref(null);

async function loadPosts(g) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.get(`/shahidar/guest-meals/${g.id}/posts`);
        posts.value = { ...posts.value, [g.id]: data.data };
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function act(g, action, body) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post(`/shahidar/guest-meals/${g.id}/${action}`, body);
        noting.value = null;
        emit('updated', data);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
