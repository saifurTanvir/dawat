<template>
    <section class="pt-2">
        <RouterLink to="/" class="text-sm text-leaf">← আজকের সব খাবার</RouterLink>

        <p v-if="loading" class="mt-6 text-muted">লোড হচ্ছে…</p>
        <div v-else-if="notFound" class="mt-6">
            <h1 class="font-serif text-2xl font-bold">এই খাবারটি আর দেখা যাচ্ছে না</h1>
            <RouterLink to="/" class="btn-primary mt-6">আশেপাশের খাবার দেখুন</RouterLink>
        </div>

        <template v-else-if="post">
            <p v-if="!available" class="mt-4 rounded-xl bg-steel p-4 font-semibold text-muted" role="status">
                এই খাবারটি শেষ হয়ে গেছে বা সময় পেরিয়ে গেছে।
            </p>

            <div class="mt-4">
                <PostCard :post="post" :kitchen-name="post.kitchen.name">
                    <template v-if="post.distance_m" #meta>
                        <span class="font-semibold text-ink">{{ distanceText(post.distance_m) }}</span>
                        · হেঁটে {{ bn(post.walk_minutes) }} মিনিট · {{ post.kitchen.name }}
                    </template>
                    <template v-if="available" #actions>
                        <ContactButtons :post="post" :show-comments="false" :open-order="$route.query.order === '1'" @gone="available = false" />
                    </template>
                </PostCard>
            </div>

            <!-- Comments -->
            <section id="comments" class="mt-8" aria-labelledby="comments-title">
                <h2 id="comments-title" class="text-lg font-semibold">
                    কমেন্ট<span v-if="comments.length" class="font-normal text-muted"> ({{ bn(comments.length) }})</span>
                </h2>

                <ul v-if="comments.length" class="mt-3 space-y-3">
                    <li v-for="c in comments" :key="c.id" class="rounded-xl p-3" :class="c.author.is_cook ? 'bg-leaf-soft' : 'bg-plate'">
                        <p class="text-sm">
                            <span class="font-semibold">{{ c.author.name }}</span>
                            <span v-if="c.author.is_cook" class="ml-1 rounded-full bg-leaf px-2 py-0.5 text-xs text-white">রাঁধুনি</span>
                            <span class="ml-1 text-muted">· {{ agoText(c.created_at) }}</span>
                        </p>
                        <p class="mt-1 whitespace-pre-line">{{ c.body }}</p>
                        <div v-if="c.can_delete || c.can_hide || c.can_report" class="mt-1 flex gap-4 text-sm">
                            <button v-if="c.can_delete" type="button" class="text-muted hover:text-chili" @click="removeComment(c)">মুছে দিন</button>
                            <button v-if="c.can_hide" type="button" class="text-muted hover:text-chili" @click="removeComment(c)">লুকান</button>
                            <button v-if="c.can_report" type="button" class="text-muted hover:text-chili" @click="openReport('comment', c.id)">রিপোর্ট</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-2 text-muted">এখনো কেউ কমেন্ট করেননি।</p>

                <template v-if="post.contact.comment && (available || isCook)">
                    <form v-if="auth.isSignedIn" class="mt-4" novalidate @submit.prevent="sendComment">
                        <label for="comment" class="sr-only">কমেন্ট লিখুন</label>
                        <textarea
                            id="comment"
                            v-model="draft"
                            class="field min-h-20"
                            maxlength="500"
                            rows="2"
                            :placeholder="isCook ? 'ক্রেতাদের উত্তর দিন' : 'প্রশ্ন করুন, যেমন: ১টায় নিতে পারব?'"
                        ></textarea>
                        <p v-if="commentError" class="error-text" role="alert">{{ commentError }}</p>
                        <button type="submit" class="btn-primary mt-3" :disabled="sending || draft.trim().length < 2">
                            {{ sending ? 'পাঠানো হচ্ছে…' : 'কমেন্ট পাঠান' }}
                        </button>
                    </form>
                    <RouterLink v-else :to="{ name: 'login', query: { next: route.fullPath } }" class="btn-quiet -ml-4 mt-3">
                        কমেন্ট করতে মোবাইল নম্বর দিয়ে ঢুকুন
                    </RouterLink>
                </template>
            </section>

            <div v-if="auth.isSignedIn && !isCook" class="mt-10 border-t border-rule pt-4">
                <button type="button" class="text-sm text-muted hover:text-chili" @click="openReport('post', post.id)">
                    এই পোস্টে কোনো সমস্যা? দাওয়াত টিমকে জানান
                </button>
            </div>
        </template>

        <!-- Report dialog -->
        <dialog ref="reportDialog" class="m-auto w-[min(26rem,calc(100%-2rem))] rounded-[var(--radius-plate)] p-0 backdrop:bg-ink/50" @close="reportDone = ''">
            <form class="p-5" method="dialog" @submit.prevent="sendReport">
                <h2 class="text-lg font-semibold">কী সমস্যা?</h2>
                <p v-if="reportDone" class="mt-3 rounded-lg bg-leaf-soft p-3" role="status">{{ reportDone }}</p>
                <template v-else>
                    <fieldset class="mt-3 space-y-2">
                        <legend class="sr-only">কারণ</legend>
                        <label v-for="r in reasons" :key="r.value" class="flex items-center gap-3 rounded-lg p-2 hover:bg-steel">
                            <input v-model="report.reason" type="radio" name="reason" :value="r.value" class="size-5 accent-leaf" />
                            <span>{{ r.label }}</span>
                        </label>
                    </fieldset>
                    <label for="report-details" class="mt-3 block text-sm font-semibold">বিস্তারিত (ইচ্ছা হলে)</label>
                    <textarea id="report-details" v-model="report.details" class="field mt-1" rows="2" maxlength="500"></textarea>
                    <p v-if="reportError" class="error-text" role="alert">{{ reportError }}</p>
                </template>
                <div class="mt-4 flex gap-3">
                    <button type="button" class="btn-quiet flex-1 border border-rule" @click="reportDialog.close()">
                        {{ reportDone ? 'ঠিক আছে' : 'বাতিল' }}
                    </button>
                    <button v-if="!reportDone" type="submit" class="btn-primary flex-1" :disabled="!report.reason || reporting">পাঠান</button>
                </div>
            </form>
        </dialog>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import ContactButtons from '@/components/ContactButtons.vue';
import PostCard from '@/components/PostCard.vue';
import { api, errorMessage } from '@/lib/api';
import { bn } from '@/lib/bn';
import { agoText, distanceText } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useFeedStore } from '@/stores/feed';

const route = useRoute();
const auth = useAuthStore();
const feed = useFeedStore();

const post = ref(null);
const available = ref(true);
const loading = ref(true);
const notFound = ref(false);
const comments = ref([]);
const draft = ref('');
const sending = ref(false);
const commentError = ref('');

const reportDialog = ref(null);
const report = reactive({ type: 'post', id: null, reason: '', details: '' });
const reporting = ref(false);
const reportError = ref('');
const reportDone = ref('');
const reasons = [
    { value: 'unsafe_food', label: 'খাবার খারাপ বা অস্বাস্থ্যকর' },
    { value: 'wrong_info', label: 'ছবি বা তথ্য সত্যি নয়' },
    { value: 'rude', label: 'অশালীন বা অপমানজনক' },
    { value: 'spam', label: 'স্প্যাম বা বিজ্ঞাপন' },
    { value: 'other', label: 'অন্য কিছু' },
];

const isCook = computed(() => Boolean(post.value?.is_mine));

async function loadComments() {
    const { data } = await api.get(`/posts/${route.params.id}/comments`);
    comments.value = data.data;
}

onMounted(async () => {
    try {
        const { data } = await api.get(`/posts/${route.params.id}`, { params: feed.position ?? {} });
        post.value = data.data;
        available.value = data.meta.is_available;
        await loadComments();
        if (route.hash === '#comments') {
            document.getElementById('comments')?.scrollIntoView();
        }
    } catch (e) {
        notFound.value = e.response?.status === 404;
    } finally {
        loading.value = false;
    }
});

async function sendComment() {
    commentError.value = '';
    sending.value = true;
    try {
        const { data } = await api.post(`/posts/${post.value.id}/comments`, { body: draft.value });
        comments.value.push(data.data);
        draft.value = '';
    } catch (e) {
        commentError.value = errorMessage(e);
    } finally {
        sending.value = false;
    }
}

async function removeComment(c) {
    try {
        await api.delete(`/comments/${c.id}`);
        comments.value = comments.value.filter((x) => x.id !== c.id);
    } catch (e) {
        commentError.value = errorMessage(e);
    }
}

function openReport(type, id) {
    Object.assign(report, { type, id, reason: '', details: '' });
    reportError.value = '';
    reportDone.value = '';
    reportDialog.value.showModal();
}

async function sendReport() {
    reporting.value = true;
    reportError.value = '';
    try {
        const { data } = await api.post('/reports', { ...report });
        reportDone.value = data.message;
    } catch (e) {
        reportError.value = errorMessage(e);
    } finally {
        reporting.value = false;
    }
}
</script>
