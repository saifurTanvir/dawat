<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-ink/55 sm:items-center sm:p-6"
                @click.self="close"
            >
                <div
                    ref="dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="join-title"
                    tabindex="-1"
                    class="relative max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-t-[var(--radius-plate)] bg-plate px-5 pt-5 pb-6 shadow-xl outline-none sm:rounded-[var(--radius-plate)]"
                    @keydown.esc="close"
                >
                    <button
                        type="button"
                        class="absolute top-2 right-2 grid size-11 place-items-center rounded-full text-muted hover:bg-steel"
                        aria-label="বন্ধ করুন"
                        @click="close"
                    >
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>

                    <img src="/brand/mark.png" alt="" width="720" height="364" class="mx-auto w-56" />

                    <h2 id="join-title" class="mt-3 text-center font-serif text-2xl font-bold">দাওয়াতে যোগ দিন</h2>
                    <p class="mt-1 text-center text-muted">
                        আমরা সবে শুরু করছি — বাড্ডা, গুলশান, বনানী, মহাখালী, রামপুরা আর বসুন্ধরায়।
                    </p>

                    <ul class="mt-5 space-y-3">
                        <li class="rounded-xl bg-steel px-4 py-3">
                            <p class="font-semibold">বাসায় রান্না করেন?</p>
                            <p class="text-sm text-muted">গৃহিণী হিসেবে যোগ দিন, পরিবারের রান্না থেকে কয়েক প্লেট বিক্রি করে ঘরে বসে আয় করুন।</p>
                        </li>
                        <li class="rounded-xl bg-steel px-4 py-3">
                            <p class="font-semibold">রোজ ঘরের খাবার চান?</p>
                            <p class="text-sm text-muted">অফিসে বা বাসায় দুপুর-রাতের নিয়মিত খাবার, পাড়ার গৃহিণীর রান্না।</p>
                        </li>
                    </ul>

                    <p class="mt-5 font-semibold">সরাসরি দাওয়াত টিমের সাথে কথা বলুন</p>
                    <a :href="`tel:${CONTACT.phoneIntl}`" class="btn-primary mt-2 gap-2">
                        <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.6a1 1 0 01-.25 1z" /></svg>
                        কল করুন {{ bn(CONTACT.phone) }}
                    </a>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <a :href="CONTACT.whatsapp" target="_blank" rel="noopener" class="btn-quiet border border-rule">হোয়াটসঅ্যাপ</a>
                        <a :href="CONTACT.facebook" target="_blank" rel="noopener" class="btn-quiet border border-rule">ফেসবুক গ্রুপ</a>
                    </div>

                    <button type="button" class="btn-quiet mt-3 w-full text-muted" @click="close">এখন না, আজকের খাবার দেখি</button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { bn } from '@/lib/bn';
import { CONTACT, JOIN_BANNER_ENABLED } from '@/lib/contact';
import { useAuthStore } from '@/stores/auth';

/**
 * F33: while Dawat is new, a visitor's first page shows who to call to join
 * as a cook or a regular customer. Once per browser session.
 */
const SEEN_KEY = 'dawat.joinBannerSeen';
const PAGES = ['feed', 'post', 'qr'];

const route = useRoute();
const auth = useAuthStore();
const open = ref(false);
const dialog = ref(null);
let timer = null;
let returnFocus = null;

function seen() {
    try {
        return sessionStorage.getItem(SEEN_KEY) === '1';
    } catch {
        return false;
    }
}

function markSeen() {
    try {
        sessionStorage.setItem(SEEN_KEY, '1');
    } catch {
        // Private mode: the popup may show again, which is fine.
    }
}

function maybeOpen() {
    if (!JOIN_BANNER_ENABLED || open.value || seen() || auth.isSignedIn || !PAGES.includes(route.name)) return;
    clearTimeout(timer);
    // Let the page draw first, so the popup does not hide a blank screen.
    timer = setTimeout(async () => {
        if (seen() || auth.isSignedIn) return;
        returnFocus = document.activeElement;
        open.value = true;
        markSeen();
        document.documentElement.style.overflow = 'hidden';
        await nextTick();
        dialog.value?.focus({ preventScroll: true });
    }, 900);
}

function close() {
    open.value = false;
    document.documentElement.style.overflow = '';
    returnFocus?.focus?.();
}

// Keep Tab inside the dialog while it is open.
function trapFocus(e) {
    if (!open.value || e.key !== 'Tab' || !dialog.value) return;
    const items = dialog.value.querySelectorAll('a[href], button');
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        last.focus();
        e.preventDefault();
    } else if (!e.shiftKey && document.activeElement === last) {
        first.focus();
        e.preventDefault();
    }
}

watch(() => route.name, maybeOpen);
onMounted(() => {
    document.addEventListener('keydown', trapFocus);
    maybeOpen();
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    document.removeEventListener('keydown', trapFocus);
    document.documentElement.style.overflow = '';
});
</script>
