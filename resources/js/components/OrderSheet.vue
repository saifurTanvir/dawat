<template>
    <!-- F29: "নিতে চাই" — book plates from this post -->
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-ink/50" @click.self="$emit('close')">
        <form
            class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-[var(--radius-plate)] bg-plate p-5 pb-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="order-title"
            novalidate
            @submit.prevent="submit"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="order-title" class="text-lg font-semibold">{{ post.title }}</h2>
                    <p class="text-sm text-muted">{{ post.kitchen.name }} · প্লেট {{ taka(post.price_paisa) }}</p>
                </div>
                <button type="button" class="btn-quiet -mr-4 min-h-10 w-auto" @click="$emit('close')">বন্ধ</button>
            </div>

            <template v-if="done">
                <p class="mt-4 rounded-xl bg-leaf-soft p-4" role="status">{{ done.message }}</p>
                <p class="mt-2 text-sm">বক্স {{ done.data.code }} · {{ bn(done.data.plates) }} প্লেট · {{ taka(done.data.total_paisa) }}</p>
                <RouterLink to="/orders" class="btn-primary mt-4">আমার অর্ডার দেখুন</RouterLink>
            </template>

            <template v-else>
                <div class="mt-5">
                    <span id="o-plates" class="mb-1 block font-semibold">কয় প্লেট</span>
                    <div class="flex w-44 items-center rounded-xl border border-rule bg-plate" role="group" aria-labelledby="o-plates">
                        <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="plates <= 1" aria-label="এক প্লেট কম" @click="plates--">−</button>
                        <output class="flex-1 text-center text-lg font-semibold" aria-live="polite">{{ bn(plates) }}</output>
                        <button type="button" class="size-12 text-2xl text-leaf disabled:text-rule" :disabled="plates >= post.order.max_plates" aria-label="এক প্লেট বেশি" @click="plates++">+</button>
                    </div>
                </div>

                <fieldset class="mt-5">
                    <legend class="mb-2 font-semibold">কীভাবে নেবেন</legend>
                    <div class="space-y-2">
                        <label v-if="post.order.delivery" class="flex cursor-pointer items-start gap-3 rounded-xl border p-3" :class="receive === 'delivery' ? 'border-leaf bg-leaf-soft' : 'border-rule'">
                            <input v-model="receive" type="radio" value="delivery" class="mt-1 size-5 accent-leaf" />
                            <span>
                                <span class="block font-semibold">শাহিদার পৌঁছে দেবেন</span>
                                <span class="text-sm text-muted">ডেলিভারি চার্জ {{ post.order.delivery_fee_paisa ? taka(post.order.delivery_fee_paisa) : 'নেই' }}</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3" :class="receive === 'self' ? 'border-leaf bg-leaf-soft' : 'border-rule'">
                            <input v-model="receive" type="radio" value="self" class="mt-1 size-5 accent-leaf" />
                            <span>
                                <span class="block font-semibold">নিজে নেব</span>
                                <span class="text-sm text-muted">গৃহিণী ফোন করে জানাবেন কোথায় নিতে হবে</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="err('receive')" class="error-text">{{ err('receive') }}</p>
                </fieldset>

                <div v-if="receive === 'delivery'" class="mt-5">
                    <label for="o-address" class="mb-1 block font-semibold">কোথায় পৌঁছাবে</label>
                    <textarea id="o-address" v-model="address" class="field min-h-20" rows="2" maxlength="255" placeholder="বাসা, রোড, ফ্ল্যাট, চেনার মতো কিছু"></textarea>
                    <p v-if="err('address')" class="error-text">{{ err('address') }}</p>
                </div>

                <div class="mt-4">
                    <label for="o-note" class="mb-1 block text-sm font-semibold">কিছু বলার থাকলে (ইচ্ছা হলে)</label>
                    <input id="o-note" v-model="note" class="field" maxlength="200" placeholder="যেমন: গেটে এসে ফোন দেবেন" />
                </div>

                <p class="mt-5 flex items-baseline justify-between border-t border-rule pt-4">
                    <span class="text-muted">মোট, খাবার হাতে পেয়ে দেবেন</span>
                    <span class="text-xl font-semibold">{{ taka(total) }}</span>
                </p>
                <p v-if="error" class="error-text mt-2" role="alert">{{ error }}</p>
                <button type="submit" class="btn-primary mt-4" :disabled="busy">{{ busy ? 'বুক হচ্ছে…' : 'বুক করুন' }}</button>
            </template>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { taka } from '@/lib/format';

const props = defineProps({ post: { type: Object, required: true } });
const emit = defineEmits(['close', 'ordered']);

const plates = ref(1);
const receive = ref(props.post.order.delivery ? 'delivery' : 'self');
const address = ref('');
const note = ref('');
const busy = ref(false);
const error = ref('');
const errors = ref({});
const done = ref(null);

const total = computed(() => props.post.price_paisa * plates.value + (receive.value === 'delivery' ? props.post.order.delivery_fee_paisa : 0));

function err(key) {
    return errors.value[key]?.[0] ?? '';
}

onMounted(async () => {
    try {
        const { data } = await api.get('/orders');
        address.value = data.last_address ?? '';
        if (!data.can_order) error.value = 'বারবার খাবার না নেওয়ায় "নিতে চাই" কিছুদিনের জন্য বন্ধ। দাওয়াত টিমের সাথে যোগাযোগ করুন।';
    } catch {
        // Prefill only.
    }
});

async function submit() {
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
        const { data } = await api.post(`/posts/${props.post.id}/orders`, {
            plates: plates.value,
            receive: receive.value,
            address: receive.value === 'delivery' ? address.value : null,
            note: note.value || null,
        });
        done.value = data;
        emit('ordered', plates.value);
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        error.value = errorMessage(e);
    } finally {
        busy.value = false;
    }
}
</script>
