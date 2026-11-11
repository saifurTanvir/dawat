<template>
    <!-- F48: shows which role the app is used as; opens the role's pages and the other roles. -->
    <div ref="root" class="relative ml-auto shrink-0">
        <button
            type="button"
            class="flex min-h-10 items-center gap-1 rounded-full border border-rule bg-plate px-3 text-sm font-semibold text-leaf-deep"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="`এখন ${MODES[mode].label} হিসেবে দেখছেন — রোল বদলান`"
            @click="open = !open"
        >
            <span aria-hidden="true">{{ MODES[mode].icon }}</span>
            <span>{{ MODES[mode].label }}</span>
            <span aria-hidden="true" class="text-xs">▾</span>
        </button>

        <div v-if="open" class="absolute right-0 z-30 mt-2 w-64 rounded-[var(--radius-plate)] border border-rule bg-plate p-2 shadow-lg" role="menu">
            <p class="px-3 pt-1 pb-2 text-xs text-muted">আপনি দেখছেন <strong class="text-ink">{{ MODES[mode].label }}</strong> হিসেবে</p>
            <template v-for="l in MODES[mode].links" :key="l.to">
                <a v-if="l.external" :href="l.to" role="menuitem" class="block rounded-lg px-3 py-2 text-sm hover:bg-leaf-soft">{{ l.label }} ↗</a>
                <RouterLink v-else :to="l.to" role="menuitem" class="block rounded-lg px-3 py-2 text-sm hover:bg-leaf-soft" :class="route.path === l.to ? 'font-semibold text-leaf' : ''" @click="open = false">{{ l.label }}</RouterLink>
            </template>

            <template v-if="others.length">
                <p class="mt-1 border-t border-rule px-3 pt-2 pb-1 text-xs text-muted">রোল বদলান</p>
                <button
                    v-for="m in others"
                    :key="m"
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-leaf-soft"
                    @click="switchTo(m)"
                >
                    <span aria-hidden="true">{{ MODES[m].icon }}</span> {{ MODES[m].label }} হিসেবে দেখুন<span v-if="MODES[m].external" aria-hidden="true">↗</span>
                </button>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { MODES, availableModes, currentMode, rememberMode } from '@/lib/mode';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const open = ref(false);
const root = ref(null);

const mode = computed(() => currentMode(auth.user, route.path));
const others = computed(() => availableModes(auth.user).filter((m) => m !== mode.value));

// Opening a page of another role (a link, a notification) makes it the current role.
watch(mode, (m) => rememberMode(m));
watch(() => route.fullPath, () => (open.value = false));

function switchTo(m) {
    rememberMode(m);
    open.value = false;
    if (MODES[m].external) {
        window.location.href = MODES[m].home;
        return;
    }
    router.push(MODES[m].home);
}

function onOutside(e) {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}
function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}
onMounted(() => {
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onKey);
});
</script>
