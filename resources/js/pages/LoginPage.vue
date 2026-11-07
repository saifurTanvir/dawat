<template>
    <section class="pt-6" aria-labelledby="login-title">
        <h1 id="login-title" class="font-serif text-2xl font-bold">
            {{ step === 'phone' ? 'মোবাইল নম্বর দিয়ে ঢুকুন' : 'SMS-এর কোডটি দিন' }}
        </h1>
        <p class="mt-1 text-muted">
            <template v-if="step === 'phone'">পাসওয়ার্ড লাগবে না। নম্বরে একটা কোড যাবে।</template>
            <template v-else>{{ phoneShown }} নম্বরে {{ bn(codeLength) }} অঙ্কের কোড পাঠানো হয়েছে।</template>
        </p>

        <form v-if="step === 'phone'" class="mt-6" novalidate @submit.prevent="sendCode">
            <label for="phone" class="mb-2 block font-semibold">মোবাইল নম্বর</label>
            <input
                id="phone"
                v-model="phone"
                class="field tracking-wide"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                placeholder="017XXXXXXXX"
                :aria-invalid="Boolean(error)"
                aria-describedby="phone-error"
                required
            />
            <p v-if="error" id="phone-error" class="error-text" role="alert">{{ error }}</p>
            <button type="submit" class="btn-primary mt-5" :disabled="busy">
                {{ busy ? 'কোড পাঠানো হচ্ছে…' : 'কোড পাঠান' }}
            </button>
        </form>

        <form v-else class="mt-6" novalidate @submit.prevent="verify">
            <label for="code" class="mb-2 block font-semibold">কোড</label>
            <input
                id="code"
                ref="codeInput"
                v-model="code"
                class="field text-center text-2xl tracking-[0.4em]"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                :maxlength="codeLength"
                :aria-invalid="Boolean(error)"
                aria-describedby="code-error"
                required
            />
            <p v-if="devAnyCode" class="mt-2 rounded-lg bg-turmeric-soft p-2 text-sm">
                লোকাল ডেভেলপমেন্ট: যেকোনো {{ bn(codeLength) }} অঙ্কের কোড চলবে, যেমন ১২৩৪৫৬।
            </p>
            <p v-if="error" id="code-error" class="error-text" role="alert">{{ error }}</p>
            <button type="submit" class="btn-primary mt-5" :disabled="busy || code.length < codeLength">
                {{ busy ? 'যাচাই হচ্ছে…' : 'ঢুকুন' }}
            </button>

            <div class="mt-4 flex items-center justify-between">
                <button type="button" class="btn-quiet -ml-4" @click="changeNumber">নম্বর বদলান</button>
                <button type="button" class="btn-quiet -mr-4" :disabled="cooldown > 0 || busy" @click="sendCode">
                    {{ cooldown > 0 ? `আবার পাঠান (${bn(cooldown)})` : 'আবার কোড পাঠান' }}
                </button>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const codeLength = 6;
const step = ref('phone');
const phone = ref('');
const code = ref('');
const error = ref('');
const busy = ref(false);
const cooldown = ref(0);
const devAnyCode = ref(false);
const codeInput = ref(null);
let timer = null;

const phoneShown = computed(() => phone.value.trim());

function startCooldown(seconds) {
    clearInterval(timer);
    cooldown.value = seconds;
    timer = setInterval(() => {
        cooldown.value -= 1;
        if (cooldown.value <= 0) {
            clearInterval(timer);
        }
    }, 1000);
}

async function sendCode() {
    error.value = '';
    busy.value = true;
    try {
        const data = await auth.requestCode(phone.value);
        devAnyCode.value = Boolean(data.dev_any_code);
        step.value = 'code';
        startCooldown(data.resend_after ?? 60);
        await nextTick();
        codeInput.value?.focus();
    } catch (e) {
        error.value = errorMessage(e);
        if (e.response?.data?.retry_after && step.value === 'code') {
            startCooldown(e.response.data.retry_after);
        }
    } finally {
        busy.value = false;
    }
}

async function verify() {
    error.value = '';
    busy.value = true;
    try {
        await auth.verifyCode(phone.value, code.value);
        const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : '/';
        router.replace(next);
    } catch (e) {
        error.value = errorMessage(e);
        code.value = '';
    } finally {
        busy.value = false;
    }
}

function changeNumber() {
    step.value = 'phone';
    code.value = '';
    error.value = '';
}

onBeforeUnmount(() => clearInterval(timer));
</script>
