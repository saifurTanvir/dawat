<template>
    <section class="pt-4" aria-labelledby="regular-title">
        <h1 id="regular-title" class="font-serif text-2xl font-bold">প্রতিদিন ঘরের খাবার</h1>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>

        <!-- Booked already: who feeds and delivers -->
        <div v-else-if="booking && !editing" class="mt-4 space-y-4">
            <div class="rounded-[var(--radius-plate)] border-2 bg-plate p-5" :class="booking.status === 'active' ? 'border-leaf' : booking.status === 'waiting' ? 'border-turmeric' : 'border-rule'">
                <p class="text-lg font-semibold" :class="booking.status === 'active' ? 'text-leaf' : 'text-ink'">{{ booking.status_label }}</p>
                <p class="mt-1 text-muted">{{ statusMessage }}</p>
                <dl class="mt-4 space-y-2">
                    <div>
                        <dt class="text-sm text-muted">বুকিং</dt>
                        <dd>{{ booking.booking_summary }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">ঠিকানা</dt>
                        <dd>{{ booking.address }}, {{ booking.area.name }}</dd>
                    </div>
                    <div v-if="booking.kitchen">
                        <dt class="text-sm text-muted">রান্না করবেন</dt>
                        <dd class="font-semibold">{{ booking.kitchen.name }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="booking.shahidar" class="rounded-[var(--radius-plate)] bg-plate p-5">
                <p class="text-sm text-muted">আপনার শাহিদার (খাবার পৌঁছে দেবেন)</p>
                <p class="mt-1 text-lg font-semibold">{{ booking.shahidar.name }}</p>
                <a :href="`tel:${booking.shahidar.phone}`" class="btn-primary mt-3">কল করুন · {{ bn(booking.shahidar.phone) }}</a>
                <p class="mt-3 text-sm text-muted">খাবার ভালো না লাগলে বা গৃহিণী বদলাতে চাইলে শাহিদারকে বলুন।</p>
            </div>

            <button type="button" class="btn-quiet w-full border border-rule" @click="startEdit">
                {{ booking.status === 'stopped' ? 'আবার চালু করতে চাই' : 'বুকিং বদলান' }}
            </button>
        </div>

        <!-- Ask / change -->
        <form v-else class="mt-4 space-y-5" novalidate @submit.prevent="save">
            <p v-if="!booking" class="text-muted">
                আপনার এলাকার একজন গৃহিণী প্রতিদিন রান্না করবেন, আর একজন শাহিদার বাসায় পৌঁছে দেবেন। দাম দেবেন খাবার হাতে পেয়ে।
            </p>

            <div>
                <label for="r-name" class="mb-1 block font-semibold">আপনার নাম</label>
                <input id="r-name" v-model="form.name" class="field" type="text" maxlength="60" autocomplete="name" />
                <p v-if="err('name')" class="error-text">{{ err('name') }}</p>
            </div>

            <div>
                <label for="r-area" class="mb-1 block font-semibold">এলাকা</label>
                <select id="r-area" v-model.number="form.area_id" class="field">
                    <option :value="null" disabled>বেছে নিন</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <p v-if="err('area_id')" class="error-text">{{ err('area_id') }}</p>
            </div>

            <div>
                <label for="r-address" class="mb-1 block font-semibold">ডেলিভারির ঠিকানা</label>
                <textarea id="r-address" v-model="form.address" class="field min-h-20" rows="2" maxlength="255" placeholder="বাসা, রোড, ফ্ল্যাট, চেনার মতো কিছু"></textarea>
                <p class="mt-1 text-sm text-muted">শুধু দাওয়াত টিম আর আপনার শাহিদার দেখবেন।</p>
                <p v-if="err('address')" class="error-text">{{ err('address') }}</p>
            </div>

            <fieldset>
                <legend class="mb-2 font-semibold">কোন বেলা</legend>
                <div class="flex flex-wrap gap-2">
                    <ChoiceChip
                        v-for="slot in mealSlots"
                        :key="slot.value"
                        type="checkbox"
                        name="meal_slots"
                        :value="true"
                        :model-value="form.meal_slots.includes(slot.value)"
                        @update:model-value="toggle(form.meal_slots, slot.value)"
                    >{{ slot.label }}</ChoiceChip>
                </div>
                <p v-if="err('meal_slots')" class="error-text">{{ err('meal_slots') }}</p>
            </fieldset>

            <div>
                <span id="r-plates" class="mb-1 block font-semibold">প্রতি বেলায় কয় প্লেট</span>
                <div class="flex w-44 items-center rounded-xl border border-rule bg-plate" role="group" aria-labelledby="r-plates">
                    <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="form.plates <= 1" aria-label="এক প্লেট কম" @click="form.plates--">−</button>
                    <output class="flex-1 text-center text-lg font-semibold" aria-live="polite">{{ bn(form.plates) }}</output>
                    <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="form.plates >= 10" aria-label="এক প্লেট বেশি" @click="form.plates++">+</button>
                </div>
            </div>

            <fieldset>
                <legend class="mb-2 font-semibold">কোন দিন</legend>
                <div class="flex flex-wrap gap-2">
                    <ChoiceChip
                        v-for="(day, i) in weekdays"
                        :key="i"
                        type="checkbox"
                        name="weekdays"
                        :value="true"
                        :model-value="form.weekdays.includes(i)"
                        @update:model-value="toggle(form.weekdays, i)"
                    >{{ day }}</ChoiceChip>
                </div>
                <p class="mt-2 text-sm text-muted">কোনো দিন লাগবে না হলে আগে থেকে শাহিদারকে জানাবেন, ওই দিনের জন্য বন্ধ থাকবে।</p>
                <p v-if="err('weekdays')" class="error-text">{{ err('weekdays') }}</p>
            </fieldset>

            <div>
                <label for="r-note" class="mb-1 block font-semibold">খাবার নিয়ে কিছু (ইচ্ছা হলে)</label>
                <textarea id="r-note" v-model="form.note" class="field min-h-20" rows="2" maxlength="300" placeholder="যেমন: ঝাল কম, ডায়াবেটিস আছে"></textarea>
            </div>

            <p v-if="error" class="error-text" role="alert">{{ error }}</p>

            <div class="flex gap-3">
                <button v-if="booking" type="button" class="btn-quiet flex-1 border border-rule" :disabled="busy" @click="editing = false">বাতিল</button>
                <button type="submit" class="btn-primary flex-[2]" :disabled="busy">
                    {{ busy ? 'পাঠানো হচ্ছে…' : booking ? 'বদল সংরক্ষণ করুন' : 'অনুরোধ পাঠান' }}
                </button>
            </div>
        </form>

        <p v-if="notice" class="mt-4 rounded-xl bg-leaf-soft p-3" role="status">{{ notice }}</p>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import ChoiceChip from '@/components/ChoiceChip.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const mealSlots = [
    { value: 'breakfast', label: 'সকালের নাস্তা' },
    { value: 'lunch', label: 'দুপুর' },
    { value: 'snack', label: 'বিকেলের নাস্তা' },
    { value: 'dinner', label: 'রাত' },
];
const weekdays = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহস্পতি', 'শুক্র', 'শনি'];

const loading = ref(true);
const busy = ref(false);
const editing = ref(false);
const booking = ref(null);
const areas = ref([]);
const error = ref('');
const errors = ref({});
const notice = ref('');

const form = reactive({ name: '', area_id: null, address: '', note: '', meal_slots: ['lunch'], plates: 1, weekdays: [0, 1, 2, 3, 4, 5, 6] });

const statusMessage = computed(() => ({
    waiting: 'দাওয়াত টিম আপনার এলাকার একজন গৃহিণী আর শাহিদার ঠিক করে ফোন দেবে।',
    active: 'বুকিংয়ের দিনগুলোতে খাবার নিজে থেকেই আসবে। দাম দেবেন খাবার হাতে পেয়ে।',
    stopped: 'নিয়মিত খাবার এখন বন্ধ আছে।',
})[booking.value?.status] ?? '');

function err(key) {
    return errors.value[key]?.[0] ?? '';
}

function toggle(list, value) {
    const i = list.indexOf(value);
    if (i >= 0) list.splice(i, 1);
    else list.push(value);
}

function fill(b) {
    Object.assign(form, {
        name: b?.name ?? auth.user?.name ?? '',
        area_id: b?.area?.id ?? null,
        address: b?.address ?? '',
        note: b?.note ?? '',
        meal_slots: [...(b?.meal_slots ?? ['lunch'])],
        plates: b?.plates ?? 1,
        weekdays: [...(b?.weekdays ?? [0, 1, 2, 3, 4, 5, 6])],
    });
}

function startEdit() {
    fill(booking.value);
    notice.value = '';
    editing.value = true;
}

onMounted(async () => {
    try {
        const [{ data: a }, { data: r }] = await Promise.all([api.get('/areas'), api.get('/regular')]);
        areas.value = a.data;
        booking.value = r.data;
        fill(r.data);
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
});

async function save() {
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
        const { data } = await api.put('/regular', { ...form, note: form.note || null });
        booking.value = data.data;
        notice.value = data.message === statusMessage.value ? '' : data.message;
        editing.value = false;
        window.scrollTo({ top: 0 });
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
