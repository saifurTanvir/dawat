<template>
    <!-- F53: anyone can apply to work as a shahidar; an admin calls them back. -->
    <section aria-labelledby="apply-title" class="pt-2">
        <template v-if="!done">
            <div class="rounded-[var(--radius-plate)] bg-leaf p-5 text-white">
                <h1 id="apply-title" class="font-serif text-2xl leading-snug font-bold">শাহিদার হয়ে আয় করুন</h1>
                <p class="mt-3 text-[1.375rem] leading-snug font-bold text-turmeric-soft">
                    মাসে {{ incomeText }} পর্যন্ত আয়ের সুযোগ
                </p>
                <p class="mt-3 leading-relaxed opacity-95">
                    নিজের পাড়াতেই গৃহিণীদের রান্না করা খাবার বাসা আর অফিসে পৌঁছে দিন। পুরুষ-মহিলা সবাই আবেদন করতে পারেন।
                </p>
                <p class="mt-2 text-sm opacity-80">আয় নির্ভর করে সপ্তাহে কত দিন আর কতগুলো ডেলিভারি করেন তার ওপর।</p>
            </div>

            <div class="mt-6">
                <h2 class="text-lg font-bold">শাহিদার কী করেন</h2>
                <ul class="mt-2 space-y-2 leading-relaxed">
                    <li v-for="line in whatTheyDo" :key="line" class="flex gap-2.5">
                        <svg viewBox="0 0 24 24" class="mt-1 size-5 shrink-0 text-leaf" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg>
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </div>

            <form class="mt-8 space-y-8" novalidate @submit.prevent="submit">
                <fieldset class="space-y-5">
                    <legend class="mb-3 text-lg font-bold">আপনার পরিচয়</legend>
                    <div>
                        <label for="a-name" class="mb-1 block font-semibold">নাম</label>
                        <input id="a-name" v-model.trim="form.name" class="field" autocomplete="name" maxlength="60" />
                        <p v-if="err('name')" class="error-text">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label for="a-phone" class="mb-1 block font-semibold">মোবাইল নম্বর</label>
                        <input id="a-phone" v-model.trim="form.phone" class="field" type="tel" inputmode="tel" autocomplete="tel" placeholder="০১৭XXXXXXXX" />
                        <p class="mt-1 text-sm text-muted">এই নম্বরে দাওয়াত টিম ফোন করবে।</p>
                        <p v-if="err('phone')" class="error-text">{{ err('phone') }}</p>
                    </div>
                    <ChipGroup label="আপনি" name="gender" :options="opts.gender" v-model="form.gender" :error="err('gender')" />
                    <div>
                        <label for="a-age" class="mb-1 block font-semibold">বয়স (ইচ্ছা হলে)</label>
                        <input id="a-age" v-model.number="form.age" class="field max-w-32" type="number" inputmode="numeric" min="18" max="70" />
                        <p v-if="err('age')" class="error-text">{{ err('age') }}</p>
                    </div>
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 text-lg font-bold">এলাকা</legend>
                    <div>
                        <label for="a-lives" class="mb-1 block font-semibold">কোথায় থাকেন</label>
                        <input id="a-lives" v-model.trim="form.lives_in" class="field" maxlength="160" placeholder="যেমন: মধ্য বাড্ডা, আলাতুন্নেছা স্কুলের কাছে" />
                        <p v-if="err('lives_in')" class="error-text">{{ err('lives_in') }}</p>
                    </div>
                    <ChipGroup
                        label="কোন এলাকায় কাজ করতে চান"
                        hint="একাধিক বেছে নিতে পারেন।"
                        name="area_ids"
                        multiple
                        :options="areaOptions"
                        v-model="form.area_ids"
                        :error="err('area_ids')"
                    />
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 text-lg font-bold">কাজ আর যানবাহন</legend>
                    <ChipGroup label="এখন কী করেন" name="job" :options="opts.job" v-model="form.job" :error="err('job')" />
                    <div v-if="form.job && form.job !== 'none'">
                        <label for="a-job-note" class="mb-1 block font-semibold">কী কাজ, কোন সময়ে (ইচ্ছা হলে)</label>
                        <input id="a-job-note" v-model.trim="form.job_note" class="field" maxlength="120" placeholder="যেমন: দোকানে সকাল ৯টা–১টা" />
                    </div>
                    <ChipGroup label="যানবাহন আছে?" name="vehicle" :options="opts.vehicle" v-model="form.vehicle" :error="err('vehicle')" />
                    <ChipGroup
                        v-if="needsLicense"
                        label="ড্রাইভিং লাইসেন্স আছে?"
                        name="has_license"
                        :options="YES_NO"
                        v-model="form.has_license"
                        :error="err('has_license')"
                    />
                    <ChipGroup label="স্মার্টফোন আছে?" hint="অ্যাপে কাজ দেখতে লাগবে।" name="has_smartphone" :options="YES_NO" v-model="form.has_smartphone" :error="err('has_smartphone')" />
                    <ChipGroup label="জাতীয় পরিচয়পত্র (NID) আছে?" name="has_nid" :options="YES_NO" v-model="form.has_nid" :error="err('has_nid')" />
                    <div>
                        <label for="a-exp" class="mb-1 block font-semibold">আগে ডেলিভারির কাজ করেছেন? (ইচ্ছা হলে)</label>
                        <input id="a-exp" v-model.trim="form.experience" class="field" maxlength="160" placeholder="যেমন: ফুডপান্ডায় ৬ মাস" />
                    </div>
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 text-lg font-bold">কখন কাজ করতে পারবেন</legend>
                    <ChipGroup label="কতক্ষণ" name="hours" :options="opts.hours" v-model="form.hours" :error="err('hours')" />
                    <ChipGroup label="কোন সময়ে" hint="একাধিক বেছে নিতে পারেন। দুপুরে সবচেয়ে বেশি কাজ।" name="slots" multiple :options="opts.slots" v-model="form.slots" :error="err('slots')" />
                    <ChipGroup label="সপ্তাহে কত দিন" name="days_per_week" :options="DAYS" v-model="form.days_per_week" :error="err('days_per_week')" />
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 text-lg font-bold">শেষ কথা</legend>
                    <ChipGroup label="দাওয়াতের কথা কোথা থেকে জানলেন? (ইচ্ছা হলে)" name="heard_from" :options="opts.heard_from" v-model="form.heard_from" />
                    <div>
                        <label for="a-note" class="mb-1 block font-semibold">আমাদের কিছু বলতে চান? (ইচ্ছা হলে)</label>
                        <textarea id="a-note" v-model.trim="form.note" class="field min-h-24" maxlength="500"></textarea>
                    </div>
                    <div>
                        <label class="flex items-start gap-3">
                            <input v-model="form.agree" type="checkbox" class="mt-1.5 size-5 shrink-0 accent-leaf" />
                            <span>দাওয়াত টিম আমাকে এই নম্বরে ফোন বা হোয়াটসঅ্যাপ করতে পারবে।</span>
                        </label>
                        <p v-if="err('agree')" class="error-text">{{ err('agree') }}</p>
                    </div>
                </fieldset>

                <div>
                    <p v-if="message" class="error-text mb-3" role="alert">{{ message }}</p>
                    <button type="submit" class="btn-primary" :disabled="sending">{{ sending ? 'পাঠানো হচ্ছে…' : 'আবেদন পাঠান' }}</button>
                    <p class="mt-3 text-center text-sm text-muted">
                        প্রশ্ন থাকলে: <a :href="`tel:${CONTACT.phoneIntl}`" class="font-semibold text-ink">{{ bn(CONTACT.phone) }}</a>
                    </p>
                </div>
            </form>
        </template>

        <div v-else class="pt-6 text-center" role="status">
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-leaf-soft text-leaf">
                <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg>
            </span>
            <h1 class="mt-4 font-serif text-2xl font-bold">আবেদন পৌঁছেছে</h1>
            <p class="mt-2 leading-relaxed text-muted">{{ done }}</p>
            <a :href="CONTACT.whatsapp" target="_blank" rel="noopener" class="btn-quiet mt-6 border border-rule">হোয়াটসঅ্যাপে কথা বলুন</a>
            <RouterLink to="/" class="btn-quiet mt-2 w-full">হোম পাতায় যান</RouterLink>
        </div>
    </section>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, reactive, ref } from 'vue';
import ChoiceChip from '@/components/ChoiceChip.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { CONTACT } from '@/lib/contact';
import { useAuthStore } from '@/stores/auth';

/** A labelled row of chips: one choice (radio) or several (checkbox). */
const ChipGroup = defineComponent({
    props: {
        label: String,
        hint: String,
        name: String,
        options: { type: Array, default: () => [] },
        modelValue: { type: [String, Number, Boolean, Array], default: null },
        multiple: Boolean,
        error: String,
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const pick = (value) => {
            if (!props.multiple) return emit('update:modelValue', value);
            const list = Array.isArray(props.modelValue) ? props.modelValue : [];
            emit('update:modelValue', list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
        };
        return () => h('fieldset', { 'data-field': props.name }, [
            h('legend', { class: 'mb-1 font-semibold' }, props.label),
            props.hint ? h('p', { class: 'mb-2 text-sm text-muted' }, props.hint) : null,
            h('div', { class: 'mt-1 flex flex-wrap gap-2' }, props.options.map((o) => h(ChoiceChip, {
                key: String(o.value),
                name: props.name,
                type: props.multiple ? 'checkbox' : 'radio',
                // ChoiceChip is checked when modelValue === value; for checkboxes that is true === true.
                value: props.multiple ? true : o.value,
                modelValue: props.multiple ? (props.modelValue ?? []).includes(o.value) : props.modelValue,
                'onUpdate:modelValue': () => pick(o.value),
            }, () => o.label))),
            props.error ? h('p', { class: 'error-text' }, props.error) : null,
        ]);
    },
});

const YES_NO = [{ value: true, label: 'আছে' }, { value: false, label: 'নেই' }];
const DAYS = [1, 2, 3, 4, 5, 6, 7].map((d) => ({ value: d, label: d === 7 ? 'প্রতিদিন' : `${bn(d)} দিন` }));

const whatTheyDo = [
    'গৃহিণীর বাসা থেকে খাবারের বক্স নিয়ে গ্রাহকের বাসা বা অফিসে পৌঁছে দেন।',
    'নিজের এলাকাতেই কাজ, অনেক দূরে যেতে হয় না।',
    'অ্যাপে দেখবেন কার কাছ থেকে নেবেন, কাকে দেবেন; দাওয়াত টিম শুরুতে সব শিখিয়ে দেবে।',
];

const auth = useAuthStore();
const opts = reactive({ gender: [], job: [], vehicle: [], needs_license: [], hours: [], slots: [], heard_from: [] });
const income = ref({ min: 20000, max: 30000 });
const areas = ref([]);
const errors = ref({});
const message = ref('');
const sending = ref(false);
const done = ref('');

const form = reactive({
    name: auth.user?.name ?? '',
    phone: auth.user?.phone ?? '',
    gender: null,
    age: null,
    lives_in: '',
    area_ids: [],
    job: null,
    job_note: '',
    vehicle: null,
    has_license: null,
    has_smartphone: null,
    has_nid: null,
    experience: '',
    hours: null,
    slots: ['lunch'],
    days_per_week: null,
    heard_from: null,
    note: '',
    agree: false,
});

const areaOptions = computed(() => areas.value.map((a) => ({ value: a.id, label: a.name })));
const needsLicense = computed(() => opts.needs_license.includes(form.vehicle));
const taka = (n) => `৳${bn(Number(n).toLocaleString('en-US'))}`;
const incomeText = computed(() => `${taka(income.value.min)}–${taka(income.value.max)}`);

function err(key) {
    return errors.value[key]?.[0] ?? errors.value[`${key}.0`]?.[0] ?? '';
}

async function submit() {
    errors.value = {};
    message.value = '';
    sending.value = true;
    try {
        const payload = { ...form, has_license: needsLicense.value ? form.has_license : null };
        const { data } = await api.post('/shahidar-applications', payload);
        done.value = data.message;
        window.scrollTo({ top: 0 });
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        message.value = Object.keys(errors.value).length ? 'কিছু ঘর ঠিক করতে হবে, লাল লেখাগুলো দেখুন।' : errorMessage(e);
        await nextTick();
        document.querySelector('.error-text:not([role=alert])')?.closest('fieldset, div')?.scrollIntoView({ block: 'center' });
    } finally {
        sending.value = false;
    }
}

onMounted(async () => {
    const [o, a] = await Promise.allSettled([api.get('/shahidar-applications/options'), api.get('/areas')]);
    if (o.status === 'fulfilled') {
        const d = o.value.data.data;
        Object.assign(opts, d);
        if (d.income) income.value = d.income;
    }
    if (a.status === 'fulfilled') areas.value = a.value.data.data;
});
</script>
