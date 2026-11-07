<template>
    <div class="rounded-[var(--radius-plate)] bg-plate p-4">
        <p class="font-semibold">কল আর মেসেজ কোন নম্বরে যাবে</p>

        <!-- Live number -->
        <p class="mt-1 text-muted">
            <template v-if="kitchen.contact_phone">
                {{ kitchen.contact_name }} — {{ kitchen.contact_phone }}
                <span class="ml-1 rounded-full bg-leaf-soft px-2 py-0.5 text-xs text-leaf-deep">যাচাই করা</span>
            </template>
            <template v-else>আপনার নিজের নম্বর</template>
        </p>

        <!-- A request is waiting -->
        <div v-if="pending" class="mt-4 rounded-xl bg-turmeric-soft p-3">
            <p class="font-semibold">অপেক্ষমাণ: {{ pending.name }} — {{ pending.phone }}</p>

            <template v-if="pending.method === 'otp'">
                <p class="mt-1 text-sm">ওই নম্বরে একটা কোড গেছে। নম্বরের মালিকের কাছ থেকে কোডটা নিয়ে এখানে দিন।</p>
                <form class="mt-3 flex gap-2" novalidate @submit.prevent="verify">
                    <label for="contact-code" class="sr-only">কোড</label>
                    <input
                        id="contact-code"
                        v-model="code"
                        class="field flex-1 text-center tracking-[0.3em] placeholder:tracking-normal"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="6"
                        placeholder="কোড"
                    />
                    <button type="submit" class="btn-primary w-auto px-6" :disabled="busy || code.length < 6">যাচাই</button>
                </form>
            </template>
            <p v-else class="mt-1 text-sm">দাওয়াত টিম ওই নম্বরে ফোন করে নিশ্চিত হলে চালু হবে। ততক্ষণ ক্রেতারা আপনার বর্তমান নম্বরে যোগাযোগ করবেন।</p>

            <button type="button" class="btn-quiet -ml-4 mt-1 min-h-10 text-sm" :disabled="busy" @click="remove">অনুরোধ বাতিল করুন</button>
        </div>

        <!-- Ask for another number -->
        <template v-else>
            <button v-if="!open" type="button" class="btn-quiet -ml-4 mt-2" @click="open = true">
                {{ kitchen.contact_phone ? 'অন্য নম্বর দিন' : 'আমার বদলে অন্য নম্বর দিন (যেমন শাহিদার)' }}
            </button>
            <button v-if="!open && kitchen.contact_phone" type="button" class="btn-quiet mt-2 min-h-10 text-sm" :disabled="busy" @click="remove">
                নিজের নম্বরে ফিরুন
            </button>

            <form v-if="open" class="mt-3 space-y-3" novalidate @submit.prevent>
                <div>
                    <label for="contact_name" class="mb-1 block text-sm font-semibold">নাম</label>
                    <input id="contact_name" v-model="form.contact_name" class="field" type="text" maxlength="60" placeholder="যেমন: করিম ভাই (শাহিদার)" />
                    <p v-if="err('contact_name')" class="error-text" role="alert">{{ err('contact_name') }}</p>
                </div>
                <div>
                    <label for="contact_phone" class="mb-1 block text-sm font-semibold">মোবাইল নম্বর</label>
                    <input id="contact_phone" v-model="form.contact_phone" class="field" type="tel" inputmode="tel" placeholder="018XXXXXXXX" />
                    <p v-if="err('contact_phone')" class="error-text" role="alert">{{ err('contact_phone') }}</p>
                </div>
                <p class="text-sm text-muted">নম্বরের মালিক রাজি কিনা নিশ্চিত হতে হয়। কীভাবে করবেন?</p>
                <button type="button" class="btn-primary" :disabled="busy" @click="request('otp')">ওই নম্বরে SMS কোড পাঠান</button>
                <button type="button" class="btn-quiet w-full border border-rule" :disabled="busy" @click="request('admin')">
                    দাওয়াত টিম ফোন করে অনুমোদন দিক
                </button>
                <button type="button" class="btn-quiet -ml-4 min-h-10 text-sm" @click="open = false">বাতিল</button>
            </form>
        </template>

        <p v-if="message && !pending" class="mt-3 text-sm text-leaf-deep" role="status">{{ message }}</p>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';

const props = defineProps({ kitchen: { type: Object, required: true } });
const emit = defineEmits(['updated']);

const open = ref(false);
const busy = ref(false);
const code = ref('');
const error = ref('');
const errors = ref({});
const message = ref('');
const form = reactive({ contact_name: '', contact_phone: '' });

const pending = computed(() => props.kitchen.pending_contact);

function err(key) {
    return errors.value[key]?.[0] ?? '';
}

async function run(call) {
    busy.value = true;
    error.value = '';
    errors.value = {};
    message.value = '';
    try {
        const { data } = await call();
        emit('updated', data.data);
        message.value = data.message ?? '';
        return true;
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        error.value = Object.keys(errors.value).length ? '' : errorMessage(e);
        return false;
    } finally {
        busy.value = false;
    }
}

async function request(method) {
    if (await run(() => api.post('/kitchen/contact', { ...form, method }))) {
        open.value = false;
        code.value = '';
    }
}

function verify() {
    return run(() => api.post('/kitchen/contact/verify', { code: code.value }));
}

function remove() {
    return run(() => api.delete('/kitchen/contact'));
}
</script>
