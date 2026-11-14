<template>
    <!-- F61: what the cook is making today, and "আজ খাব না" / "এই খাবার খাব না". -->
    <div class="rounded-[var(--radius-plate)] bg-plate p-5">
        <h2 class="font-semibold">আজ কী রান্না হচ্ছে</h2>
        <div v-for="m in meals" :key="m.slot" class="mt-3 border-t border-rule pt-3 first:mt-2 first:border-0 first:pt-0">
            <p class="text-sm text-muted">{{ m.label }}</p>
            <p class="font-semibold">{{ m.title || 'গৃহিণী এখনো জানাননি' }}</p>

            <!-- Their answer -->
            <p v-if="m.choice === 'skip'" class="mt-1 rounded-lg bg-steel px-3 py-2 text-sm">আজ এই বেলায় খাবেন না বলেছেন — খাবার আসবে না।</p>
            <div v-else-if="m.choice === 'change'" class="mt-1 rounded-lg px-3 py-2 text-sm" :class="m.handled ? 'bg-leaf-soft' : 'bg-turmeric-soft'">
                <p>এই খাবার খাবেন না, চেয়েছেন: <strong>{{ m.wish }}</strong></p>
                <p v-if="m.handled" class="mt-1">ব্যবস্থা হয়েছে<template v-if="m.handled_note">: {{ m.handled_note }}</template></p>
                <p v-else class="mt-1 text-muted">আপনার শাহিদার শিগগিরই ফোন করে অন্য খাবারের ব্যবস্থা করবেন।</p>
            </div>

            <template v-if="m.can_choose">
                <div v-if="asking !== m.slot" class="mt-2 flex flex-wrap gap-2">
                    <button v-if="m.choice !== 'eat' && m.can_undo" type="button" class="btn-quiet min-h-10 border border-rule px-3 text-sm" :disabled="busy" @click="send(m, 'eat')">খাব</button>
                    <button v-if="m.choice !== 'skip'" type="button" class="btn-quiet min-h-10 border border-rule px-3 text-sm" :disabled="busy" @click="send(m, 'skip')">আজ খাব না</button>
                    <button v-if="m.choice !== 'change' && m.title" type="button" class="btn-quiet min-h-10 border border-rule px-3 text-sm" :disabled="busy" @click="ask(m)">এই খাবার খাব না</button>
                </div>
                <form v-else class="mt-2" @submit.prevent="send(m, 'change')">
                    <label :for="`wish-${m.slot}`" class="mb-1 block text-sm font-semibold">কী খেতে চান?</label>
                    <input :id="`wish-${m.slot}`" v-model.trim="wish" class="field text-base" maxlength="300" placeholder="যেমন: মাছ ছাড়া কিছু, শুধু ডাল-ভাত" />
                    <p class="mt-1 text-xs text-muted">আপনার শাহিদার আর দাওয়াত টিম জানবেন, গৃহিণী নন।</p>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="btn-primary min-h-10 w-auto px-4 text-sm" :disabled="busy || wish.length < 2">জানান</button>
                        <button type="button" class="btn-quiet min-h-10 px-3 text-sm" @click="asking = null">থাক</button>
                    </div>
                </form>
            </template>
        </div>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { api, errorMessage } from '@/lib/api';

defineProps({ meals: { type: Array, required: true } });
const emit = defineEmits(['updated']);

const busy = ref(false);
const error = ref('');
const asking = ref(null);
const wish = ref('');

function ask(m) {
    asking.value = m.slot;
    wish.value = '';
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
