<template>
    <!-- F53: anyone can apply to work as a shahidar; an admin calls them back. F99: four fields only. -->
    <section aria-labelledby="apply-title" class="pt-2">
        <template v-if="!done">
            <!-- F99: the pay per parcel first, then a four-field form; the rest is asked on the call. -->
            <div class="rounded-[var(--radius-plate)] bg-leaf p-5 text-white">
                <h1 id="apply-title" class="font-serif text-2xl leading-snug font-bold">এলাকা ম্যানেজার হয়ে আয় করুন</h1>
                <p class="mt-3 text-[1.375rem] leading-snug font-bold text-turmeric-soft">প্রতি পার্সেলে {{ taka(perParcel) }}</p>
                <table class="mt-3 w-full text-left">
                    <thead class="text-sm opacity-80">
                        <tr><th class="py-1 font-normal">দিনে পার্সেল</th><th class="py-1 font-normal">দিনে আয়</th><th class="py-1 font-normal">মাসে (২৬ দিন)</th></tr>
                    </thead>
                    <tbody class="font-semibold">
                        <tr v-for="n in STEPS" :key="n" class="border-t border-white/20">
                            <td class="py-1.5">{{ bn(n) }}টা</td>
                            <td class="py-1.5">{{ taka(n * perParcel) }}</td>
                            <td class="py-1.5">{{ taka(n * perParcel * 26) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 text-sm leading-relaxed opacity-90">নিজের পাড়াতেই কাজ — গৃহিণীদের রান্না করা খাবার বাসা আর অফিসে পৌঁছে দেওয়া। শুরুতে পার্সেল কম থাকে, এলাকায় গ্রাহক বাড়লে আয়ও বাড়ে। পুরুষ-মহিলা সবাই আবেদন করতে পারেন।</p>
            </div>

            <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
                <h2 class="text-lg font-bold">মাত্র ৪টা তথ্য দিন — বাকি কথা আমরা ফোনে জেনে নেব</h2>
                <div>
                    <label for="a-name" class="mb-1 block font-semibold">নাম</label>
                    <input id="a-name" v-model.trim="form.name" class="field" autocomplete="name" maxlength="60" />
                    <p v-if="err('name')" class="error-text">{{ err('name') }}</p>
                </div>
                <div>
                    <label for="a-phone" class="mb-1 block font-semibold">মোবাইল নম্বর</label>
                    <input id="a-phone" v-model.trim="form.phone" class="field" type="tel" inputmode="tel" autocomplete="tel" placeholder="০১৭XXXXXXXX" />
                    <p v-if="err('phone')" class="error-text">{{ err('phone') }}</p>
                </div>
                <div>
                    <label for="a-lives" class="mb-1 block font-semibold">কোথায় থাকেন</label>
                    <input id="a-lives" v-model.trim="form.lives_in" class="field" maxlength="160" placeholder="যেমন: মধ্য বাড্ডা, আলাতুন্নেছা স্কুলের কাছে" />
                    <p v-if="err('lives_in')" class="error-text">{{ err('lives_in') }}</p>
                </div>
                <ChipGroup label="যানবাহন" name="vehicle" :options="opts.vehicle" v-model="form.vehicle" :error="err('vehicle')" />

                <div>
                    <p v-if="message" class="error-text mb-3" role="alert">{{ message }}</p>
                    <button type="submit" class="btn-primary" :disabled="sending">{{ sending ? 'পাঠানো হচ্ছে…' : 'আবেদন পাঠান' }}</button>
                    <p class="mt-3 text-center text-sm text-muted">আবেদন পাঠালে দাওয়াত টিম এই নম্বরে ফোন বা হোয়াটসঅ্যাপ করবে।</p>
                    <p class="mt-1 text-center text-sm text-muted">
                        প্রশ্ন থাকলে: <a :href="`tel:${CONTACT.phoneIntl}`" class="font-semibold text-ink">{{ bn(CONTACT.phone) }}</a>
                    </p>
                </div>
            </form>

            <div class="mt-8">
                <h2 class="text-lg font-bold">এলাকা ম্যানেজার কী করেন</h2>
                <ul class="mt-2 space-y-2 leading-relaxed">
                    <li v-for="line in whatTheyDo" :key="line" class="flex gap-2.5">
                        <svg viewBox="0 0 24 24" class="mt-1 size-5 shrink-0 text-leaf" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg>
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </div>
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
import { defineComponent, h, nextTick, onMounted, reactive, ref } from 'vue';
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


const whatTheyDo = [
    'নিজের এলাকার দায়িত্বে থাকেন: সকালে গৃহিণী আর গ্রাহকদের সাথে আজকের খাবার মিলিয়ে নেন।',
    'খাবার নেওয়া ও পৌঁছানো — গৃহিণীর বাসা থেকে বক্স নিয়ে গ্রাহকের বাসা বা অফিসে দেন (হেঁটে, সাইকেলে বা বাইকে)।',
    'নিজের বাসার এলাকাতেই কাজ, অনেক দূরে যেতে হয় না। এলাকায় গ্রাহক বাড়লে আয়ও বাড়ে।',
    'অ্যাপে দেখবেন কার কাছ থেকে নেবেন, কাকে দেবেন; দাওয়াত টিম শুরুতে সব শিখিয়ে দেবে।',
];

const auth = useAuthStore();
const opts = reactive({ gender: [], job: [], vehicle: [], needs_license: [], hours: [], slots: [], heard_from: [] });
const perParcel = ref(15);
const STEPS = [20, 50, 100];
const errors = ref({});
const message = ref('');
const sending = ref(false);
const done = ref('');

const form = reactive({
    name: auth.user?.name ?? '',
    phone: auth.user?.phone ?? '',
    lives_in: '',
    vehicle: null,
    agree: true, // F99: the note under the button says the team will call.
});

const taka = (n) => `৳${bn(Number(n).toLocaleString('en-US'))}`;

function err(key) {
    return errors.value[key]?.[0] ?? errors.value[`${key}.0`]?.[0] ?? '';
}

async function submit() {
    errors.value = {};
    message.value = '';
    sending.value = true;
    try {
        const payload = { ...form };
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
    const o = (await Promise.allSettled([api.get('/shahidar-applications/options')]))[0];
    if (o.status === 'fulfilled') {
        const d = o.value.data.data;
        Object.assign(opts, d);
        if (d.income?.per_parcel) perParcel.value = d.income.per_parcel;
    }
});
</script>
