<template>
    <div>
        <!-- F29: book in the app -->
        <button v-if="canOrder" type="button" class="btn-primary mb-2" @click="startOrder">নিতে চাই</button>
        <div class="flex gap-2">
            <button
                v-if="post.contact.message"
                type="button"
                :class="canOrder ? 'inline-flex min-h-12 flex-1 items-center justify-center rounded-xl border-2 border-leaf px-4 font-semibold text-leaf hover:bg-leaf-soft' : 'btn-primary flex-1'"
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
        <OrderSheet v-if="ordering" :post="post" @close="ordering = false" @ordered="onOrdered" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import OrderSheet from '@/components/OrderSheet.vue';
import { useAuthStore } from '@/stores/auth';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { useFeedStore } from '@/stores/feed';

const props = defineProps({
    post: { type: Object, required: true },
    /** The post page shows comments below; the feed links to them. */
    showComments: { type: Boolean, default: true },
    /** Open the order sheet at once (after signing in to order). */
    openOrder: { type: Boolean, default: false },
});
const emit = defineEmits(['gone']);

const feed = useFeedStore();
const busy = ref(null);
const error = ref('');
const copied = ref(false);
const auth = useAuthStore();
const router = useRouter();
const ordering = ref(false);

const canOrder = computed(() => props.post.order?.enabled && !props.post.is_mine && props.post.plates_left > 0);

function startOrder() {
    if (!auth.isSignedIn) {
        router.push({ name: 'login', query: { next: `/p/${props.post.id}?order=1` } });
        return;
    }
    ordering.value = true;
}

function onOrdered(plates) {
    // Show the new count straight away; the feed reloads it later anyway.
    // eslint-disable-next-line vue/no-mutating-props
    props.post.plates_left = Math.max(0, props.post.plates_left - plates);
}

onMounted(() => {
    if (props.openOrder && canOrder.value && auth.isSignedIn) ordering.value = true;
});

async function request(type) {
    const { data } = await api.post(`/posts/${props.post.id}/contact`, { type, qr: feed.qr?.code ?? null });
    return data.data;
}

/** Logs the tap, then opens the phone dialer or WhatsApp with the number the server hands back. */
async function contact(type) {
    error.value = '';
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

/** The post's share card as a File, or null if there is none yet or it can't be fetched. */
async function cardFile() {
    if (!props.post.share_image_url) return null;
    try {
        const res = await fetch(props.post.share_image_url);
        if (!res.ok) return null;
        const blob = await res.blob();
        return new File([blob], `dawat-${props.post.id}.jpg`, { type: blob.type || 'image/jpeg' });
    } catch {
        return null;
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
        // Best: send the share card itself (F19), so the picture shows even where links don't preview.
        const card = await cardFile();
        if (card && navigator.canShare?.({ files: [card] })) {
            await navigator.share({ files: [card], title: props.post.title, text: `${text}\n${url}` });
            return;
        }
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
