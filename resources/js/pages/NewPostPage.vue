<template>
    <section class="pt-4" aria-labelledby="new-post-title">
        <RouterLink to="/cook" class="text-sm text-leaf">← আমার রান্নাঘর</RouterLink>
        <h1 id="new-post-title" class="mt-2 font-serif text-2xl font-bold">আজকের রান্না পোস্ট</h1>

        <!-- Progress: the steps really are a sequence, so they are numbered -->
        <ol class="mt-4 grid grid-cols-4 gap-1.5" aria-label="ধাপ">
            <li v-for="(s, i) in steps" :key="s" class="text-center text-xs">
                <span class="block h-1.5 rounded-full" :class="i <= step ? 'bg-leaf' : 'bg-rule'"></span>
                <span class="mt-1 block" :class="i === step ? 'font-semibold text-ink' : 'text-muted'" :aria-current="i === step ? 'step' : undefined">
                    {{ s }}
                </span>
            </li>
        </ol>

        <form class="mt-6" novalidate @submit.prevent="next">
            <!-- Step 1: photos -->
            <div v-show="step === 0">
                <h2 class="text-lg font-semibold">খাবারের ছবি দিন</h2>
                <p class="mt-1 text-muted">প্লেটে সাজানো খাবারের ১ থেকে ৪টা ছবি। প্রথমটা সবার আগে দেখাবে।</p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <figure v-for="(photo, i) in photos" :key="photo.url" class="relative overflow-hidden rounded-xl bg-leaf-soft">
                        <img :src="photo.url" :alt="`ছবি ${bn(i + 1)}`" class="aspect-square w-full object-cover" />
                        <span v-if="i === 0" class="absolute top-2 left-2 rounded-full bg-leaf px-2 py-0.5 text-xs text-white">প্রথম ছবি</span>
                        <div class="absolute inset-x-0 bottom-0 flex justify-between bg-gradient-to-t from-ink/70 p-1.5">
                            <button v-if="i > 0" type="button" class="rounded-lg px-2 py-1 text-xs text-white" @click="makeFirst(i)">প্রথমে আনুন</button>
                            <span v-else></span>
                            <button type="button" class="rounded-lg px-2 py-1 text-xs text-white" :aria-label="`ছবি ${bn(i + 1)} বাদ দিন`" @click="removePhoto(i)">বাদ দিন</button>
                        </div>
                    </figure>

                    <template v-if="photos.length < maxPhotos">
                        <label class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-leaf/40 bg-plate text-center text-leaf">
                            <span class="text-3xl leading-none" aria-hidden="true">+</span>
                            <span class="mt-1 font-semibold">ছবি তুলুন</span>
                            <input type="file" accept="image/*" capture="environment" class="sr-only" @change="addPhotos" />
                        </label>
                        <label class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-rule bg-plate text-center text-muted">
                            <span class="font-semibold">গ্যালারি থেকে</span>
                            <input type="file" accept="image/*" multiple class="sr-only" @change="addPhotos" />
                        </label>
                    </template>
                </div>
                <p v-if="preparing" class="mt-3 text-sm text-muted">ছবি ছোট করা হচ্ছে…</p>
            </div>

            <!-- Step 2: food, price, plates, time -->
            <div v-show="step === 1" class="space-y-5">
                <div>
                    <label for="title" class="mb-1 block font-semibold">খাবারের নাম</label>
                    <input id="title" v-model="form.title" class="field" type="text" maxlength="100" placeholder="যেমন: ভাত, মুরগির ঝোল, ডাল" />
                    <p v-if="err('title')" class="error-text">{{ err('title') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="price" class="mb-1 block font-semibold">এক প্লেটের দাম (৳)</label>
                        <input id="price" v-model.number="form.price_taka" class="field" type="number" inputmode="numeric" min="20" max="2000" placeholder="১৫০" />
                        <p v-if="err('price_taka')" class="error-text">{{ err('price_taka') }}</p>
                    </div>
                    <div>
                        <span id="plates-label" class="mb-1 block font-semibold">কয় প্লেট</span>
                        <div class="flex items-center rounded-xl border border-rule bg-plate" role="group" aria-labelledby="plates-label">
                            <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="form.plates_total <= 1" aria-label="এক প্লেট কম" @click="form.plates_total--">−</button>
                            <output class="flex-1 text-center text-lg font-semibold" aria-live="polite">{{ bn(form.plates_total) }}</output>
                            <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="form.plates_total >= 20" aria-label="এক প্লেট বেশি" @click="form.plates_total++">+</button>
                        </div>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 font-semibold">কোন বেলার খাবার</legend>
                    <div class="flex flex-wrap gap-2">
                        <ChoiceChip v-for="slot in mealSlots" :key="slot.value" v-model="form.meal_slot" :value="slot.value" name="meal_slot">
                            {{ slot.label }}
                        </ChoiceChip>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 font-semibold">কতক্ষণ পাওয়া যাবে</legend>
                    <div class="flex flex-wrap gap-2">
                        <ChoiceChip v-for="h in hourOptions" :key="h" v-model="form.available_hours" :value="h" name="available_hours">
                            {{ bn(h) }} ঘণ্টা
                        </ChoiceChip>
                    </div>
                    <p class="mt-2 text-sm text-muted">এরপর পোস্টটা নিজে থেকে বন্ধ হয়ে যাবে।</p>
                </fieldset>
            </div>

            <!-- Step 3: constraints -->
            <div v-show="step === 2">
                <h2 class="text-lg font-semibold">খাবার সম্পর্কে জানান</h2>
                <p class="mt-1 text-muted">যেগুলো মেলে সেগুলোতে চাপ দিন। ক্রেতারা এগুলো দেখে বেছে নেন।</p>
                <fieldset v-for="group in tagGroups" :key="group.type" class="mt-5">
                    <legend class="mb-2 font-semibold">{{ group.label }}</legend>
                    <div class="flex flex-wrap gap-2">
                        <ChoiceChip
                            v-for="tag in group.tags"
                            :key="tag.id"
                            :model-value="form.tag_ids.includes(tag.id)"
                            :value="true"
                            type="checkbox"
                            :name="`tags-${group.type}`"
                            @update:model-value="toggleTag(tag, group)"
                        >
                            {{ tag.name }}
                        </ChoiceChip>
                    </div>
                </fieldset>
                <p v-if="err('tag_ids')" class="error-text">{{ err('tag_ids') }}</p>
            </div>

            <!-- Step 4: note + preview -->
            <div v-show="step === 3">
                <label for="note" class="block font-semibold">আপনার কথা (ইচ্ছা হলে)</label>
                <p class="mt-1 text-sm text-muted">সত্যি কথাটাই লিখুন। যেমন “লবণ একটু কম হয়েছে, যারা কম লবণ খান তাদের জন্য ভালো।”</p>
                <textarea id="note" v-model="form.note" class="field mt-2 min-h-24" maxlength="300" rows="3"></textarea>
                <p class="mt-1 text-right text-xs text-muted">{{ bn(form.note.length) }}/৩০০</p>

                <h2 class="mt-6 mb-3 font-semibold">ক্রেতারা এভাবে দেখবেন</h2>
                <PostCard :post="preview" :kitchen-name="kitchenName" />
            </div>

            <p v-if="error" class="error-text mt-4" role="alert">{{ error }}</p>

            <div class="mt-8 flex gap-3">
                <button v-if="step > 0" type="button" class="btn-quiet flex-1 border border-rule" :disabled="busy" @click="back">পেছনে</button>
                <button type="submit" class="btn-primary flex-[2]" :disabled="busy || !canContinue">
                    <template v-if="step < 3">পরের ধাপ</template>
                    <template v-else-if="busy">পোস্ট হচ্ছে… {{ uploadProgress ? bn(uploadProgress) + '%' : '' }}</template>
                    <template v-else>পোস্ট করুন</template>
                </button>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import ChoiceChip from '@/components/ChoiceChip.vue';
import PostCard from '@/components/PostCard.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { currentMealSlot } from '@/lib/format';
import { compressPhoto } from '@/lib/image';

const router = useRouter();

const steps = ['ছবি', 'খাবার ও দাম', 'বৈশিষ্ট্য', 'নোট ও পোস্ট'];
const maxPhotos = 4;
const hourOptions = [1, 2, 3, 4, 6];
const mealSlots = [
    { value: 'breakfast', label: 'সকালের নাস্তা' },
    { value: 'lunch', label: 'দুপুর' },
    { value: 'snack', label: 'বিকেলের নাস্তা' },
    { value: 'dinner', label: 'রাত' },
];
const tagTypes = [
    { type: 'diet', label: 'খাবারের ধরন', single: false },
    { type: 'spice', label: 'ঝাল', single: true },
    { type: 'oil', label: 'তেল', single: true },
    { type: 'warning', label: 'জেনে রাখুন', single: false },
    { type: 'other', label: 'অন্যান্য', single: false },
];

const step = ref(0);
const busy = ref(false);
const preparing = ref(false);
const uploadProgress = ref(0);
const error = ref('');
const errors = ref({});
const photos = ref([]); // { file, url }
const tags = ref([]);
const kitchenName = ref('');

const form = reactive({
    title: '',
    price_taka: null,
    plates_total: 2,
    meal_slot: currentMealSlot(),
    available_hours: 3,
    tag_ids: [],
    note: '',
});

const tagGroups = computed(() =>
    tagTypes
        .map((g) => ({ ...g, tags: tags.value.filter((t) => t.type === g.type) }))
        .filter((g) => g.tags.length),
);

const canContinue = computed(() => {
    if (preparing.value) return false;
    if (step.value === 0) return photos.value.length > 0;
    if (step.value === 1) return form.title.trim().length >= 2 && form.price_taka >= 20;
    return true;
});

const preview = computed(() => ({
    title: form.title || 'খাবারের নাম',
    price_paisa: (form.price_taka || 0) * 100,
    plates_left: form.plates_total,
    plates_total: form.plates_total,
    meal_slot_label: mealSlots.find((m) => m.value === form.meal_slot)?.label,
    available_until: new Date(Date.now() + form.available_hours * 3600_000).toISOString(),
    note: form.note.trim(),
    tags: tags.value.filter((t) => form.tag_ids.includes(t.id)),
    images: photos.value.map((p) => ({ url: p.url })),
}));

function err(key) {
    return errors.value[key]?.[0] ?? '';
}

onMounted(async () => {
    try {
        const [{ data: t }, { data: k }] = await Promise.all([api.get('/tags'), api.get('/kitchen')]);
        tags.value = t.data;
        kitchenName.value = k.data.display_name;
        if (!k.data.can_post) {
            router.replace({ name: 'cook' });
        }
    } catch (e) {
        error.value = errorMessage(e);
    }
});

async function addPhotos(event) {
    const files = [...event.target.files].slice(0, maxPhotos - photos.value.length);
    event.target.value = '';
    preparing.value = true;
    try {
        for (const file of files) {
            const small = await compressPhoto(file);
            photos.value.push({ file: small, url: URL.createObjectURL(small) });
        }
    } finally {
        preparing.value = false;
    }
}

function removePhoto(i) {
    URL.revokeObjectURL(photos.value[i].url);
    photos.value.splice(i, 1);
}

function makeFirst(i) {
    const [p] = photos.value.splice(i, 1);
    photos.value.unshift(p);
}

function toggleTag(tag, group) {
    const has = form.tag_ids.includes(tag.id);
    if (group.single) {
        const sameGroup = group.tags.map((t) => t.id);
        form.tag_ids = form.tag_ids.filter((id) => !sameGroup.includes(id));
        if (!has) form.tag_ids.push(tag.id);
    } else {
        form.tag_ids = has ? form.tag_ids.filter((id) => id !== tag.id) : [...form.tag_ids, tag.id];
    }
}

function back() {
    error.value = '';
    step.value = Math.max(0, step.value - 1);
}

async function next() {
    error.value = '';
    if (step.value < 3) {
        step.value++;
        window.scrollTo({ top: 0 });
        return;
    }
    await publish();
}

// Which step a server-side field error belongs to, so we can jump back there.
const fieldStep = { images: 0, title: 1, price_taka: 1, plates_total: 1, meal_slot: 1, available_hours: 1, tag_ids: 2, note: 3 };

async function publish() {
    busy.value = true;
    errors.value = {};
    uploadProgress.value = 0;
    const body = new FormData();
    photos.value.forEach((p) => body.append('images[]', p.file));
    body.append('title', form.title.trim());
    body.append('price_taka', String(form.price_taka));
    body.append('plates_total', String(form.plates_total));
    body.append('meal_slot', form.meal_slot);
    body.append('available_hours', String(form.available_hours));
    form.tag_ids.forEach((id) => body.append('tag_ids[]', String(id)));
    if (form.note.trim()) body.append('note', form.note.trim());

    try {
        await api.post('/posts', body, {
            timeout: 120000,
            onUploadProgress: (e) => {
                if (e.total) uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            },
        });
        router.push({ name: 'cook', query: { posted: 1 } });
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        error.value = errorMessage(e);
        const keys = Object.keys(errors.value).map((k) => k.split('.')[0]);
        const firstStep = Math.min(...keys.map((k) => fieldStep[k] ?? 3));
        if (Number.isFinite(firstStep)) step.value = firstStep;
    } finally {
        busy.value = false;
    }
}

onBeforeUnmount(() => photos.value.forEach((p) => URL.revokeObjectURL(p.url)));
</script>
