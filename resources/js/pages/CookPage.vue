<template>
    <section class="pt-4" aria-labelledby="cook-title">
        <h1 id="cook-title" class="font-serif text-2xl font-bold">আমার রান্নাঘর</h1>
        <PushToggle off-title="নতুন অর্ডার এলে ফোনে জানুন" />
        <ContactHours />

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>

        <!-- Not registered yet -->
        <form v-else-if="!kitchen" class="mt-4" novalidate @submit.prevent="register">
            <p class="text-muted">
                পরিবারের জন্য রান্না থেকে এক-দুই প্লেট আলাদা করে পাড়ার মানুষের কাছে বিক্রি করুন।
                দাওয়াত টিম আপনার সাথে কথা বলে অনুমোদন দেবে।
            </p>
            <label for="display_name" class="mt-6 mb-2 block font-semibold">রান্নাঘরের নাম</label>
            <input
                id="display_name"
                v-model="displayName"
                class="field"
                type="text"
                maxlength="60"
                placeholder="যেমন: রহিমা খালার রান্নাঘর"
                :aria-invalid="Boolean(error)"
                aria-describedby="name-help name-error"
            />
            <p id="name-help" class="mt-2 text-sm text-muted">ক্রেতারা এই নামটা দেখবেন। নিজের নাম না দিতে চাইলে ডাকনাম দিন।</p>
            <p v-if="error" id="name-error" class="error-text" role="alert">{{ error }}</p>
            <button type="submit" class="btn-primary mt-6" :disabled="busy">
                {{ busy ? 'নিবন্ধন হচ্ছে…' : 'দাওয়াতে যোগ দিন' }}
            </button>
        </form>

        <!-- Registered: show where they stand -->
        <div v-else class="mt-4">
            <template v-if="kitchen.can_post">
                <RouterLink :to="{ name: 'new-post' }" class="btn-primary">আজকের রান্না পোস্ট করুন</RouterLink>
                <RouterLink :to="{ name: 'my-posts' }" class="btn-quiet mt-2 w-full border border-rule">আমার পোস্ট ও প্লেট বাকি</RouterLink>
                <RouterLink :to="{ name: 'sales' }" class="btn-quiet mt-2 mb-6 w-full border border-rule">যা বিক্রি করেছি · পাওনা · রিভিউ</RouterLink>
                <DailyMenu />
            </template>
            <!-- F26: regular customers today -->
            <section v-if="bookings && (bookings.regular_customers || bookings.slots.length)" class="mb-6 rounded-[var(--radius-plate)] bg-plate p-5" aria-labelledby="regular-title">
                <h2 id="regular-title" class="font-semibold">আজকের অর্ডার</h2>
                <p v-if="bookings.regular_customers" class="text-sm text-muted">নিয়মিত গ্রাহক {{ bn(bookings.regular_customers) }} জন · সর্বোচ্চ {{ bn(bookings.max_regular_customers) }}</p>
                <p v-if="!bookings.slots.length" class="mt-3 text-muted">আজ কারো বুকিং নেই।</p>
                <div v-for="slot in bookings.slots" :key="slot.meal_slot" class="mt-4 border-t border-rule pt-3">
                    <p class="font-semibold">আজ {{ slot.label }}</p>
                    <p v-if="slot.waiting_plates" class="mt-1">
                        নিয়মিত গ্রাহকদের জন্য রান্না করতে হবে <strong>{{ bn(slot.waiting_plates) }} প্লেট</strong> ({{ bn(slot.waiting_people) }} জন)।
                        পোস্ট দিলে এই প্লেটগুলো আগে যাবে, বাকিটা ফিডে।
                    </p>
                    <ul v-if="slot.orders.length" class="mt-1 space-y-0.5 text-sm text-muted">
                        <li v-for="o in slot.orders" :key="o.code">
                            বক্স {{ o.code }} · {{ o.name }} · {{ bn(o.plates) }} প্লেট · {{ o.status_label }}
                            <span v-if="o.kind === 'oneoff'" class="ml-1 rounded-full bg-turmeric-soft px-2 text-xs text-ink">{{ o.receive === 'self' ? 'নিজে নেবেন' : 'একবারের' }}</span>
                            <span class="mt-0.5 flex flex-wrap gap-x-3">
                                <a v-if="o.phone" :href="`tel:${o.phone}`" class="font-semibold text-leaf">ক্রেতাকে ফোন</a>
                                <button v-if="o.can_hand" type="button" class="font-semibold text-leaf" :disabled="busy" @click="orderAct(o, 'handed', `${taka(o.total_paisa)} পেয়ে খাবার দিয়েছেন?`)">দিয়েছি</button>
                                <button v-if="o.can_decline" type="button" class="font-semibold text-chili" :disabled="busy" @click="orderAct(o, 'decline', 'এই অর্ডার দিতে পারছেন না? ক্রেতাকে জানানো হবে।')">দিতে পারছি না</button>
                            </span>
                        </li>
                    </ul>
                    <!-- F38: tell the shahidar the food is ready -->
                    <div v-if="slot.can_ready" class="mt-3 rounded-xl bg-leaf-soft p-3">
                        <p v-if="slot.ready_at" class="text-sm">
                            ✅ শাহিদারকে জানানো হয়েছে {{ clockText(slot.ready_at) }}<template v-if="slot.ready_note"> · “{{ slot.ready_note }}”</template>
                        </p>
                        <template v-if="readySlot === slot.meal_slot">
                            <label :for="`ready-${slot.meal_slot}`" class="mb-1 block text-sm font-semibold">শাহিদারের জন্য কিছু বলবেন? (ঐচ্ছিক)</label>
                            <input
                                :id="`ready-${slot.meal_slot}`"
                                v-model="readyNote"
                                class="field text-base"
                                maxlength="200"
                                placeholder="যেমন: ২টা পর্যন্ত বাসায় আছি, তারপর দারোয়ানের কাছে"
                            />
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="btn-primary w-auto px-4" :disabled="busy" @click="sendReady(slot)">জানান</button>
                                <button type="button" class="btn-quiet" @click="readySlot = null">থাক</button>
                            </div>
                        </template>
                        <button v-else type="button" class="btn-primary mt-1" :disabled="busy" @click="openReady(slot)">
                            {{ slot.ready_at ? 'আবার জানান' : `${placeOf(slot.label)} খাবার রেডি — শাহিদারকে জানান` }}
                        </button>
                    </div>
                    <button
                        v-if="slot.waiting_plates"
                        type="button"
                        class="btn-quiet -ml-4 mt-1 text-chili"
                        :disabled="busy"
                        @click="cookOff(slot)"
                    >আজ {{ slot.label }} রান্না নেই</button>
                </div>
                <p v-if="bookingNotice" class="mt-3 rounded-lg bg-leaf-soft p-3 text-sm" role="status">{{ bookingNotice }}</p>
            </section>
            <!-- F46: what the team or a shahidar did for her today -->
            <section v-if="bookings?.acted_for?.length" class="mb-6 rounded-[var(--radius-plate)] bg-turmeric-soft p-5">
                <h2 class="font-semibold">আজ আপনার হয়ে যা করা হয়েছে</h2>
                <p v-for="(a, i) in bookings.acted_for" :key="i" class="mt-1 text-sm"><span class="text-muted">{{ clockText(a.at) }}</span> · {{ a.who }} · {{ a.details }}</p>
            </section>

            <div class="rounded-[var(--radius-plate)] bg-plate p-5" :class="statusTone.border">
                <p class="text-sm text-muted">{{ kitchen.display_name }}</p>
                <p class="mt-1 text-lg font-semibold" :class="statusTone.text">{{ kitchen.status_label }}</p>
                <p class="mt-2 text-muted">{{ statusMessage }}</p>
                <p v-if="kitchen.rejection_reason" class="mt-3 rounded-lg bg-steel p-3 text-sm">
                    কারণ: {{ kitchen.rejection_reason }}
                </p>
            </div>

            <div v-if="!kitchen.is_set_up" class="mt-6 rounded-[var(--radius-plate)] bg-turmeric-soft p-5">
                <p class="font-semibold">সেটআপ বাকি আছে</p>
                <p class="mt-1 text-muted">বাসার লোকেশন আর এলাকা দিন। এটা ছাড়া খাবার পোস্ট করা যাবে না।</p>
                <RouterLink :to="{ name: 'cook-setup' }" class="btn-primary mt-4">সেটআপ করুন</RouterLink>
            </div>
            <RouterLink v-else :to="{ name: 'cook-setup' }" class="btn-quiet -ml-4 mt-4">
                লোকেশন ও এলাকা বদলান
            </RouterLink>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import ContactHours from '@/components/ContactHours.vue';
import DailyMenu from '@/components/DailyMenu.vue';
import PushToggle from '@/components/PushToggle.vue';
import { api, errorMessage } from '@/lib/api';
import { bn, placeOf } from '@/lib/bn';
import { clockText, taka } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const kitchen = ref(null);
const loading = ref(true);
const displayName = ref('');
const error = ref('');
const busy = ref(false);
const bookings = ref(null);
const bookingNotice = ref('');

async function loadBookings() {
    try {
        const { data } = await api.get('/my/bookings');
        bookings.value = data.data;
    } catch {
        bookings.value = null;
    }
}

async function orderAct(order, path, question) {
    if (!window.confirm(question)) return;
    busy.value = true;
    try {
        const { data } = await api.post(`/my/orders/${order.id}/${path}`);
        bookingNotice.value = data.message;
        await loadBookings();
    } catch (e) {
        bookingNotice.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

// F38
const readySlot = ref(null);
const readyNote = ref('');

function openReady(slot) {
    readySlot.value = slot.meal_slot;
    readyNote.value = slot.ready_note ?? '';
}

async function sendReady(slot) {
    busy.value = true;
    try {
        const { data } = await api.post('/my/bookings/ready', { meal_slot: slot.meal_slot, note: readyNote.value || null });
        bookingNotice.value = data.message;
        readySlot.value = null;
        await loadBookings();
    } catch (e) {
        bookingNotice.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

async function cookOff(slot) {
    if (!window.confirm(`আজ ${slot.label} রান্না নেই? নিয়মিত গ্রাহকদের SMS-এ জানানো হবে।`)) return;
    busy.value = true;
    try {
        const { data } = await api.post('/my/bookings/off', { meal_slot: slot.meal_slot });
        bookingNotice.value = data.message;
        await loadBookings();
    } catch (e) {
        bookingNotice.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}

const messages = {
    pending: 'দাওয়াত টিম শীঘ্রই আপনার সাথে ফোনে যোগাযোগ করবে। অনুমোদনের পর রান্না পোস্ট করতে পারবেন।',
    approved: 'অনুমোদন হয়ে গেছে। রান্না হলেই ছবি তুলে পোস্ট দিন।',
    rejected: 'এখন অনুমোদন দেওয়া যায়নি। প্রশ্ন থাকলে দাওয়াত টিমের সাথে যোগাযোগ করুন।',
    suspended: 'আপনার রান্নাঘর সাময়িক বন্ধ আছে। দাওয়াত টিমের সাথে যোগাযোগ করুন।',
};

const statusMessage = computed(() => messages[kitchen.value?.status] ?? '');
const statusTone = computed(() => {
    switch (kitchen.value?.status) {
        case 'approved':
            return { border: 'border-2 border-leaf', text: 'text-leaf' };
        case 'pending':
            return { border: 'border-2 border-turmeric', text: 'text-ink' };
        default:
            return { border: 'border-2 border-chili', text: 'text-chili' };
    }
});

onMounted(async () => {
    try {
        const { data } = await api.get('/kitchen');
        kitchen.value = data.data;
        if (kitchen.value.status === 'approved') loadBookings();
    } catch (e) {
        if (e.response?.status !== 404) {
            error.value = errorMessage(e);
        }
    } finally {
        loading.value = false;
    }
});

async function register() {
    error.value = '';
    busy.value = true;
    try {
        const { data } = await api.post('/kitchen', { display_name: displayName.value });
        kitchen.value = data.data;
        auth.loaded = false;
        await auth.load();
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
