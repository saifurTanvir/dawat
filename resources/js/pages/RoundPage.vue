<template>
    <!-- F95: one office's round, house by house on the map, then the office -->
    <section class="pt-4" aria-labelledby="round-title">
        <RouterLink to="/shahidar" class="text-sm font-semibold text-leaf"
            >← আজকের তালিকা</RouterLink
        >

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <p v-else-if="error && !round" class="error-text mt-6" role="alert">
            {{ error }}
        </p>

        <template v-else-if="round">
            <p class="mt-3 text-sm font-semibold text-muted">
                <span aria-hidden="true">🏢</span> অফিস রাউন্ড ·
                {{ round.drop.meal_slot_label }}
            </p>
            <h1 id="round-title" class="font-serif text-2xl font-bold">
                {{ round.office.name }}
            </h1>
            <p class="text-sm text-muted">{{ round.office.address }}</p>

            <!-- Progress -->
            <div class="mt-3" aria-hidden="true">
                <div class="h-2 overflow-hidden rounded-full bg-steel">
                    <div
                        class="h-full rounded-full bg-leaf transition-all"
                        :style="{
                            width: `${round.total ? (round.picked / round.total) * 100 : 0}%`,
                        }"
                    ></div>
                </div>
            </div>
            <p class="mt-1 text-sm">
                {{ bn(round.picked) }} / {{ bn(round.total) }} জন গৃহিণীর বক্স
                তোলা হয়েছে
            </p>

            <p
                v-if="notice"
                class="mt-4 rounded-xl bg-leaf-soft p-3"
                role="status"
            >
                {{ notice }}
            </p>
            <p
                v-if="error"
                class="mt-4 rounded-xl border-2 border-chili bg-chili/10 p-3 font-semibold text-chili"
                role="alert"
            >
                {{ error }}
            </p>

            <!-- Not started: the plan, and one button -->
            <template v-if="!round.started">
                <div
                    class="mt-4 rounded-[var(--radius-plate)] bg-turmeric-soft p-4"
                >
                    <p class="font-semibold">
                        বের হওয়ার সময় "রওনা দিচ্ছি" চাপুন।
                    </p>
                    <p class="mt-1 text-sm">
                        প্রথম গৃহিণী সাথে সাথে খবর পাবেন; বাকিরা আপনি পৌঁছানোর প্রায়
                        ৩০ মিনিট আগে। ফোনের লোকেশন চালু থাকলে আপনার কাছের বাসা
                        দিয়ে শুরু হবে।
                    </p>
                    <button
                        type="button"
                        class="btn-primary mt-3"
                        :disabled="busy"
                        @click="start"
                    >
                        রওনা দিচ্ছি
                    </button>
                </div>
            </template>

            <!-- The next house: one big card -->
            <article
                v-if="next"
                class="mt-4 rounded-[var(--radius-plate)] border-2 border-leaf bg-plate p-4"
                aria-labelledby="next-title"
            >
                <p class="text-sm font-semibold text-leaf">
                    {{ round.started ? "এখন যাবেন" : "প্রথমে যাবেন" }} ·
                    {{ bn(next.position) }} নম্বর বাসা
                </p>
                <h2 id="next-title" class="mt-1 text-xl font-bold">
                    {{ next.name }}
                </h2>
                <p class="mt-1">
                    {{ next.address || "ঠিকানা নেই — ফোন করে জেনে নিন" }}
                </p>
                <p class="mt-1 text-sm">
                    <strong>{{ bn(next.boxes) }}টা বক্স</strong>
                    <template v-if="next.eta">
                        · পৌঁছাবেন আনুমানিক {{ next.eta }}</template
                    >
                </p>
                <p
                    v-if="next.reply === 'ready'"
                    class="mt-2 rounded-lg bg-leaf-soft px-3 py-1.5 text-sm font-semibold"
                >
                    ✓ গৃহিণী জানিয়েছেন বক্স তৈরি
                </p>
                <p
                    v-else-if="next.ready_at"
                    class="mt-2 rounded-lg bg-turmeric-soft px-3 py-1.5 text-sm font-semibold"
                >
                    গৃহিণী জানিয়েছেন {{ next.ready_at }}-এ তৈরি হবে
                </p>
                <p
                    v-if="!next.has_pin"
                    class="mt-2 rounded-lg bg-turmeric-soft px-3 py-1.5 text-sm"
                >
                    এই বাসার পিন নেই — ম্যাপে ঠিকানা খোঁজা হবে। ফোন করে পথ জেনে
                    নিন।
                </p>

                <a
                    v-if="next.map_url"
                    :href="next.map_url"
                    target="_blank"
                    rel="noopener"
                    class="btn-primary mt-3 flex items-center justify-center gap-2"
                >
                    <span aria-hidden="true">🗺️</span> পথ দেখান
                </a>
                <div
                    class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-steel/50 px-3 py-2 text-sm"
                >
                    <span>{{ next.phone }}</span>
                    <CallButtons :phone="next.phone" :contact="next.contact" />
                </div>
                <div
                    v-if="round.started"
                    class="mt-3 grid grid-cols-[1fr_auto] gap-2"
                >
                    <button
                        type="button"
                        class="btn-primary bg-leaf"
                        :disabled="busy"
                        @click="picking = next"
                    >
                        ✓ বক্স নিয়েছি
                    </button>
                    <button
                        type="button"
                        class="btn-quiet border-2 border-rule px-4 font-semibold"
                        :disabled="busy"
                        @click="later(next)"
                    >
                        পরে আসব
                    </button>
                </div>
            </article>

            <!-- All houses done: the office -->
            <article
                v-else-if="round.started || round.picked"
                class="mt-4 rounded-[var(--radius-plate)] border-2 border-turmeric bg-plate p-4"
                aria-labelledby="office-title"
            >
                <p class="text-sm font-semibold text-leaf">
                    সব বাসার বক্স তোলা হয়েছে
                </p>
                <h2 id="office-title" class="mt-1 text-xl font-bold">
                    এখন অফিসে যান: {{ round.office.name }}
                </h2>
                <p class="mt-1">{{ round.office.address }}</p>
                <p v-if="round.office.note" class="text-sm text-muted">
                    {{ round.office.note }}
                </p>
                <a
                    v-if="round.office.map_url"
                    :href="round.office.map_url"
                    target="_blank"
                    rel="noopener"
                    class="btn-primary mt-3 flex items-center justify-center gap-2"
                >
                    <span aria-hidden="true">🗺️</span> অফিসের পথ দেখান
                </a>
                <p v-if="!round.office.has_pin" class="mt-2 text-xs text-muted">
                    অফিসের পিন নেই — ম্যাপে ঠিকানা খোঁজা হবে।
                </p>
            </article>

            <!-- Handover at the office (and the full card) -->
            <OfficeDropCard
                v-if="!next && round.picked"
                :drop="round.drop"
                in-round
                @updated="load"
                @notice="notice = $event"
            />

            <!-- The whole list -->
            <h2 class="mt-6 text-lg font-semibold">সব বাসা</h2>
            <ol class="mt-2 space-y-2">
                <li
                    v-for="s in round.stops"
                    :key="s.id"
                    class="flex items-start justify-between gap-3 rounded-xl bg-plate p-3"
                    :class="
                        s.picked
                            ? 'opacity-60'
                            : s.id === round.next_id
                              ? 'ring-2 ring-leaf'
                              : ''
                    "
                >
                    <div>
                        <p class="font-semibold">
                            {{ bn(s.position) }}. {{ s.name }}
                            <span v-if="s.picked" class="text-leaf">✓</span>
                        </p>
                        <p class="text-sm text-muted">
                            {{ s.address }} · {{ bn(s.boxes) }}টা বক্স
                        </p>
                        <p v-if="!s.picked" class="text-xs text-muted">
                            <template v-if="s.eta"
                                >আনুমানিক {{ s.eta }}</template
                            >
                            <template v-if="s.told === 'now'">
                                · জানেন আপনি আসছেন</template
                            >
                            <template v-else-if="s.told === 'soon'">
                                · ৩০ মিনিট আগের খবর পেয়েছেন</template
                            >
                            <template v-if="s.reply === 'ready'">
                                · তৈরি</template
                            >
                            <template v-else-if="s.ready_at">
                                · {{ s.ready_at }}-এ তৈরি হবে</template
                            >
                            <template v-if="!s.has_pin"> · পিন নেই</template>
                        </p>
                    </div>
                    <a
                        v-if="!s.picked && s.map_url"
                        :href="s.map_url"
                        target="_blank"
                        rel="noopener"
                        class="shrink-0 text-sm font-semibold text-leaf"
                        >ম্যাপ</a
                    >
                </li>
                <li class="rounded-xl border-2 border-dashed border-rule p-3">
                    <p class="font-semibold">
                        <span aria-hidden="true">🏢</span> শেষে অফিস:
                        {{ round.office.name }}
                    </p>
                </li>
            </ol>
        </template>

        <StickerScanner
            v-if="picking"
            title="ব্যাগের স্টিকার স্ক্যান করুন"
            allow-skip
            @close="picking = null"
            @found="finishPickup"
        />
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import CallButtons from "@/components/CallButtons.vue";
import OfficeDropCard from "@/components/OfficeDropCard.vue";
import StickerScanner from "@/components/StickerScanner.vue";
import { api, errorMessage } from "@/lib/api";
import { bn } from "@/lib/bn";

const route = useRoute();
const round = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref("");
const notice = ref("");
const picking = ref(null);
let timer = null;

const next = computed(
    () => round.value?.stops.find((s) => s.id === round.value.next_id) || null,
);
const base = computed(() => `/shahidar/drops/${route.params.id}`);

async function load() {
    try {
        const { data } = await api.get(`${base.value}/round`);
        round.value = data.data;
        error.value = "";
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function send(path, body = {}) {
    busy.value = true;
    error.value = "";
    try {
        const { data } = await api.post(path, body);
        if (data.data?.stops) round.value = data.data;
        else await load();
        notice.value = data.message || "";
        return true;
    } catch (e) {
        error.value = errorMessage(e);
        return false;
    } finally {
        busy.value = false;
    }
}

/** Where the phone is now, if it tells us within a few seconds. */
function here() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve({});
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolve({}),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 },
        );
    });
}

async function start() {
    busy.value = true;
    const where = await here();
    busy.value = false;
    await send(`${base.value}/round/start`, where);
}

async function finishPickup(sticker) {
    const stop = picking.value;
    picking.value = null;
    if (
        await send(`${base.value}/pickup`, {
            kitchen_id: stop.kitchen_id,
            sticker: sticker || null,
        })
    ) {
        await load();
        notice.value = next.value
            ? `${stop.name}-এর বক্স নেওয়া হলো। এবার ${next.value.name} — উনি খবর পেয়েছেন।`
            : `${stop.name}-এর বক্স নেওয়া হলো। এবার অফিসে চলুন।`;
    }
}

function later(stop) {
    send(`${base.value}/round/stops/${stop.id}/later`);
}

onMounted(() => {
    load();
    // Cooks' answers ("10 more minutes") come in while he travels.
    timer = setInterval(() => !busy.value && !picking.value && load(), 60000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
