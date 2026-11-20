<template>
    <!-- F61: what the cook is making today, and "আজ খাব না" / "এই খাবার খাব না". -->
    <div class="rounded-[var(--radius-plate)] bg-plate p-5">
        <h2 class="font-semibold">আজ কী রান্না হচ্ছে</h2>
        <div v-for="m in meals" :key="m.slot" class="mt-3 border-t border-rule pt-3 first:mt-2 first:border-0 first:pt-0">
            <p class="text-sm text-muted">{{ m.label }}</p>
            <p class="font-semibold">{{ m.title || 'গৃহিণী এখনো জানাননি' }}</p>

            <!-- Their answer -->
            <div v-if="m.choice === 'pick' && m.picked" class="mt-1 rounded-lg bg-leaf-soft px-3 py-2 text-sm">
                <p>আজ নিচ্ছেন: <strong>{{ m.picked.title }}</strong></p>
                <p class="text-muted">{{ m.picked.kitchen }} · {{ taka(m.picked.price_paisa) }} — আপনার শাহিদার পৌঁছে দেবেন।</p>
            </div>
            <p v-else-if="m.choice === 'skip'" class="mt-1 rounded-lg bg-steel px-3 py-2 text-sm">আজ এই বেলায় খাবেন না বলেছেন — খাবার আসবে না।</p>
            <div v-else-if="m.choice === 'change'" class="mt-1 rounded-lg px-3 py-2 text-sm" :class="m.handled ? 'bg-leaf-soft' : 'bg-turmeric-soft'">
                <p>এই খাবার খাবেন না, চেয়েছেন: <strong>{{ m.wish }}</strong></p>
                <p v-if="m.handled" class="mt-1">ব্যবস্থা হয়েছে<template v-if="m.handled_note">: {{ m.handled_note }}</template></p>
                <p v-else class="mt-1 text-muted">আপনার শাহিদার শিগগিরই ফোন করে অন্য খাবারের ব্যবস্থা করবেন।</p>
            </div>

            <template v-if="m.can_choose">
                <!-- Three answers; "খাব" is chosen until they say otherwise. -->
                <div v-if="asking !== m.slot" class="mt-2 grid grid-cols-3 gap-2" role="radiogroup" :aria-label="`${m.label}: খাবেন?`">
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="m.choice === 'eat'"
                        :class="[btn, m.choice === 'eat' ? on : off]"
                        :disabled="busy || (m.choice !== 'eat' && !m.can_undo)"
                        @click="m.choice === 'eat' && m.confirmed ? null : send(m, 'eat')"
                    >
                        <span v-if="m.choice === 'eat'" aria-hidden="true">✓ </span>খাব
                    </button>
                    <button type="button" role="radio" :aria-checked="m.choice === 'skip'" :class="[btn, m.choice === 'skip' ? onNo : off]" :disabled="busy" @click="m.choice === 'skip' ? null : send(m, 'skip')">আজ খাব না</button>
                    <button type="button" role="radio" :aria-checked="m.choice === 'change'" :class="[btn, m.choice === 'change' ? onNo : off]" :disabled="busy || !m.title" @click="m.choice === 'change' ? null : ask(m)">এই খাবার খাব না</button>
                </div>
                <p v-if="asking !== m.slot && m.choice === 'eat'" class="mt-1.5 text-xs text-muted">
                    {{ m.confirmed ? 'আপনি খাবেন বলেছেন। খাবার আসবে।' : 'কিছু না বললেও খাবার আসবে। নিশ্চিত করতে "খাব" চাপুন।' }}
                </p>
                <!-- F84: not her cook's dish today — say what you want, or pick another cook's food nearby. -->
                <div v-else-if="asking === m.slot && !writing" class="mt-2 space-y-2">
                    <p class="text-sm font-semibold">তাহলে কী করবেন?</p>
                    <button type="button" class="btn-primary min-h-11 text-sm" @click="nearby = m">এলাকার অন্য খাবার দেখুন</button>
                    <button type="button" class="min-h-11 w-full rounded-xl border border-rule px-3 text-sm font-semibold" @click="writing = true">কী খেতে চান লিখে জানান</button>
                    <button type="button" class="btn-quiet min-h-10 px-3 text-sm" @click="asking = null">থাক</button>
                </div>
                <form v-else-if="asking === m.slot" class="mt-2" @submit.prevent="send(m, 'change')">
                    <label :for="`wish-${m.slot}`" class="mb-1 block text-sm font-semibold">কী খেতে চান?</label>
                    <input :id="`wish-${m.slot}`" v-model.trim="wish" class="field text-base" maxlength="300" placeholder="যেমন: মাছ ছাড়া কিছু, শুধু ডাল-ভাত" />
                    <p class="mt-1 text-xs text-muted">আপনার শাহিদার আর দাওয়াত টিম জানবেন, গৃহিণী নন।</p>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="btn-primary min-h-10 w-auto px-4 text-sm" :disabled="busy || wish.length < 2">জানান</button>
                        <button type="button" class="btn-quiet min-h-10 px-3 text-sm" @click="asking = null">থাক</button>
                    </div>
                </form>
                <button
                    v-if="asking !== m.slot && m.choice !== 'pick'"
                    type="button"
                    class="mt-1 text-sm font-semibold text-leaf underline-offset-2 hover:underline"
                    @click="nearby = m"
                >
                    এলাকার অন্য গৃহিণীদের আজকের খাবার দেখুন
                </button>
            </template>
        </div>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <NearbyMealsSheet v-if="nearby" :meal="nearby" @close="nearby = null" @picked="onPicked" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import NearbyMealsSheet from '@/components/NearbyMealsSheet.vue';
import { api, errorMessage } from '@/lib/api';
import { taka } from '@/lib/format';

defineProps({ meals: { type: Array, required: true } });
const emit = defineEmits(['updated']);

// Segmented buttons: green when eating, chili outline when not.
const btn = 'min-h-11 rounded-xl border px-2 text-sm font-semibold leading-tight disabled:opacity-50';
const on = 'border-leaf bg-leaf text-white';
const onNo = 'border-chili bg-chili/10 text-chili';
const off = 'border-rule bg-plate text-ink';

const busy = ref(false);
const error = ref('');
const asking = ref(null);
const wish = ref('');
const writing = ref(false);
const nearby = ref(null);

function ask(m) {
    asking.value = m.slot;
    writing.value = false;
    wish.value = '';
}

function onPicked(data) {
    nearby.value = null;
    asking.value = null;
    emit('updated', data);
}

async function send(m, choice) {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/regular/choices', { meal_slot: m.slot, choice, wish: choice === 'change' ? wish.value : null });
        asking.value = null;
        emit('updated', data);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
