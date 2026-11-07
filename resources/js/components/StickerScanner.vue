<template>
    <!-- F27: scan a box sticker with the camera, or type its 8-letter code -->
    <div class="fixed inset-0 z-50 flex flex-col bg-ink/90 p-4 text-white" role="dialog" aria-modal="true" aria-labelledby="scan-title">
        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
            <div class="flex items-center justify-between">
                <h2 id="scan-title" class="font-semibold">{{ title }}</h2>
                <button type="button" class="btn-quiet -mr-4 min-h-10 text-white" @click="$emit('close')">বন্ধ</button>
            </div>
            <div class="relative mt-3 overflow-hidden rounded-xl bg-black">
                <video ref="video" class="aspect-square w-full object-cover" muted playsinline></video>
                <p v-if="cameraError" class="absolute inset-0 flex items-center justify-center p-6 text-center">{{ cameraError }}</p>
            </div>
            <form class="mt-4" @submit.prevent="submitTyped">
                <label for="sticker-code" class="mb-1 block text-sm">ক্যামেরা কাজ না করলে স্টিকারের নম্বর লিখুন</label>
                <div class="flex gap-2">
                    <input id="sticker-code" v-model="typed" class="field flex-1 uppercase text-ink" maxlength="9" autocomplete="off" placeholder="ABCD EFGH" />
                    <button type="submit" class="btn-primary w-auto px-5" :disabled="typed.replace(/\s/g, '').length !== 8">ঠিক আছে</button>
                </div>
            </form>
            <button v-if="allowSkip" type="button" class="btn-quiet mt-4 text-white underline" @click="$emit('found', '')">স্টিকার ছাড়াই পিকআপ</button>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    title: { type: String, default: 'বক্সের স্টিকার স্ক্যান করুন' },
    allowSkip: { type: Boolean, default: false },
});
const emit = defineEmits(['found', 'close']);

const video = ref(null);
const typed = ref('');
const cameraError = ref('');
let scanner = null;

onMounted(async () => {
    try {
        const { default: QrScanner } = await import('qr-scanner');
        scanner = new QrScanner(video.value, (result) => {
            scanner?.stop();
            emit('found', result.data);
        }, { preferredCamera: 'environment', highlightScanRegion: true, maxScansPerSecond: 8 });
        await scanner.start();
    } catch {
        cameraError.value = 'ক্যামেরা চালু করা যায়নি। ব্রাউজারকে ক্যামেরার অনুমতি দিন, অথবা নিচে নম্বর লিখুন।';
    }
});

onBeforeUnmount(() => {
    scanner?.destroy();
    scanner = null;
});

function submitTyped() {
    emit('found', typed.value);
}
</script>
