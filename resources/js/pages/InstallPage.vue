<template>
    <section class="pt-2 pb-6" aria-labelledby="install-title">
        <img src="/brand/lockup.png" alt="দাওয়াত — পাড়ার ঘরের খাবার" width="800" height="761" class="mx-auto w-44" />
        <h1 id="install-title" class="mt-4 text-center font-serif text-2xl font-bold">দাওয়াত অ্যাপ ফোনে রাখুন</h1>
        <p class="mt-2 text-center text-muted">একবার ইনস্টল করলে হোম স্ক্রিনে দাওয়াতের আইকন থাকবে। চাপলেই খুলবে, কিছু টাইপ করতে হবে না। Play Store লাগে না, জায়গাও প্রায় লাগে না।</p>

        <div v-if="install.standalone || install.installed" class="mt-6 rounded-[var(--radius-plate)] bg-leaf-soft p-5 text-center">
            <p class="font-semibold">✅ দাওয়াত অ্যাপ ইনস্টল করা আছে।</p>
            <p class="mt-1 text-sm text-muted">হোম স্ক্রিনে "দাওয়াত" আইকন চাপলেই খুলবে।</p>
            <RouterLink to="/" class="btn-primary mt-4">আজকের খাবার দেখুন</RouterLink>
        </div>

        <template v-else>
            <!-- One tap on Android Chrome -->
            <button v-if="install.canPrompt" type="button" class="btn-primary mt-6 gap-2 text-lg" @click="installNow">
                <span aria-hidden="true">📲</span> অ্যাপ ইনস্টল করুন
            </button>

            <!-- Facebook / WhatsApp's own browser cannot install -->
            <div v-else-if="install.inAppBrowser" class="mt-6 rounded-[var(--radius-plate)] bg-turmeric-soft p-5">
                <p class="font-semibold">এই পাতাটা ফেসবুক বা হোয়াটসঅ্যাপের ভেতরে খোলা আছে</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm">
                    <li>ওপরে ডানদিকের <strong>⋮</strong> বা <strong>…</strong> চাপুন।</li>
                    <li><strong>"Chrome-এ খুলুন"</strong> (Open in Chrome / Open in browser) চাপুন।</li>
                    <li>Chrome-এ খুললে এখানে "অ্যাপ ইনস্টল করুন" বাটন আসবে।</li>
                </ol>
                <button type="button" class="btn-quiet -ml-4 mt-2" @click="copyLink">{{ copied ? 'লিংক কপি হয়েছে ✓' : 'লিংক কপি করুন' }}</button>
            </div>

            <div v-else-if="install.platform === 'ios'" class="mt-6 rounded-[var(--radius-plate)] bg-plate p-5">
                <p class="font-semibold">iPhone-এ (Safari দিয়ে খুলুন)</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>নিচের <strong>শেয়ার</strong> বাটন চাপুন (চারকোনা বাক্স থেকে ওপরে তীর ⬆️)।</li>
                    <li>নিচে নেমে <strong>"Add to Home Screen"</strong> চাপুন।</li>
                    <li>ওপরে ডানদিকে <strong>"Add"</strong> চাপুন।</li>
                </ol>
            </div>

            <div v-else class="mt-6 rounded-[var(--radius-plate)] bg-plate p-5">
                <p class="font-semibold">Android ফোনে (Chrome দিয়ে)</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>ওপরে ডানদিকের <strong>⋮</strong> চাপুন।</li>
                    <li><strong>"অ্যাপ ইনস্টল করুন"</strong> বা <strong>"হোম স্ক্রিনে যোগ করুন"</strong> চাপুন।</li>
                    <li>আবার <strong>"ইনস্টল"</strong> চাপুন।</li>
                </ol>
                <p class="mt-2 text-sm text-muted">কম্পিউটারে Chrome হলে ঠিকানার ঘরের ডানদিকে ইনস্টলের চিহ্ন থাকে।</p>
            </div>
        </template>

        <div class="mt-8 border-t border-rule pt-6 text-center">
            <p class="font-semibold">অন্য কাউকে পাঠান</p>
            <p class="mt-1 text-sm text-muted">গৃহিণী, শাহিদার বা গ্রাহককে হোয়াটসঅ্যাপে লিংক পাঠান, অথবা তাঁর ফোনের ক্যামেরা দিয়ে নিচের QR স্ক্যান করতে বলুন।</p>
            <a :href="whatsappShare" target="_blank" rel="noopener" class="btn-quiet mt-3 border border-rule">হোয়াটসঅ্যাপে পাঠান</a>
            <img src="/app/qr.svg" alt="dawat.cloud/app-এর QR কোড" width="200" height="200" class="mx-auto mt-4 w-48 rounded-xl bg-white p-2" />
            <p class="mt-1 text-sm text-muted">{{ appUrl.replace('https://', '') }}</p>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';
import { install, promptInstall } from '@/lib/pwa';

const appUrl = `${window.location.origin}/app`;
const whatsappShare = 'https://wa.me/?text=' + encodeURIComponent(`দাওয়াত অ্যাপ ফোনে রাখুন — পাড়ার ঘরের খাবার: ${appUrl}`);
const copied = ref(false);

async function installNow() {
    await promptInstall();
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(appUrl);
        copied.value = true;
    } catch {
        window.prompt('লিংকটা কপি করুন', appUrl);
    }
}
</script>
