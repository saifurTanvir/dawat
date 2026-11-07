<template>
    <div>
        <div class="flex gap-2">
            <button
                v-if="post.contact.message"
                type="button"
                class="btn-primary flex-1"
                :disabled="busy !== null"
                @click="contact('message')"
            >
                {{ busy === 'message' ? 'খোলা হচ্ছে…' : 'মেসেজ করুন' }}
            </button>
            <button
                v-if="post.contact.call"
                type="button"
                class="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl border-2 border-leaf px-4 font-semibold text-leaf hover:bg-leaf-soft"
                :disabled="busy !== null"
                @click="contact('call')"
            >
                {{ busy === 'call' ? 'খোলা হচ্ছে…' : 'কল করুন' }}
            </button>
            <slot />
        </div>
        <div class="mt-1 flex justify-between">
            <RouterLink
                v-if="showComments && post.contact.comment"
                :to="{ name: 'post', params: { id: post.id }, hash: '#comments' }"
                class="btn-quiet -ml-4 min-h-10 text-sm"
            >
                কমেন্ট<template v-if="post.comments_count"> ({{ bn(post.comments_count) }})</template>
            </RouterLink>
            <span v-else></span>
            <button type="button" class="btn-quiet -mr-4 min-h-10 text-sm" :disabled="busy !== null" @click="share">
                {{ copied ? 'লিংক কপি হয়েছে' : 'শেয়ার করুন' }}
            </button>
        </div>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useFeedStore } from '@/stores/feed';

const props = defineProps({
    post: { type: Object, required: true },
    /** The post page shows comments below; the feed links to them. */
    showComments: { type: Boolean, default: true },
});
const emit = defineEmits(['gone']);

const feed = useFeedStore();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const busy = ref(null);
const error = ref('');
const copied = ref(false);

async function request(type) {
    const { data } = await api.post(`/posts/${props.post.id}/contact`, { type, qr: feed.qr?.code ?? null });
    return data.data;
}

/** Logs the tap, then opens the phone dialer or WhatsApp with the number the server hands back. */
async function contact(type) {
    error.value = '';
    if (!auth.isSignedIn) {
        // Numbers are only for signed-in buyers; come straight back here after the OTP.
        router.push({ name: 'login', query: { next: route.fullPath, why: 'contact' } });
        return;
    }
    busy.value = type;
    try {
        const data = await request(type);
        window.location.href = type === 'call' ? data.tel : data.whatsapp_url;
    } catch (e) {
        error.value = errorMessage(e);
        if (e.response?.status === 410) emit('gone', props.post.id);
    } finally {
        // Leave the button busy briefly so a double tap doesn't open two chats.
        setTimeout(() => (busy.value = null), 1200);
    }
}

async function share() {
    error.value = '';
    let url = `${window.location.origin}/p/${props.post.id}`;
    try {
        url = (await request('share')).share_url;
    } catch {
        // Sharing still works without the log entry.
    }
    const text = `${props.post.title} — ${props.post.kitchen.name}, দাওয়াতে`;
    try {
        if (navigator.share) {
            await navigator.share({ title: props.post.title, text, url });
            return;
        }
        await navigator.clipboard.writeText(`${text}\n${url}`);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        // The buyer closed the share sheet; nothing to report.
    }
}
</script>
