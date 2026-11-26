<template>
    <Teleport to="body">
        <div v-if="asking.open" class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-4 sm:items-center" @click.self="answer(false)">
            <div
                ref="box"
                class="w-full max-w-sm rounded-[var(--radius-plate)] bg-plate p-5 shadow-xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="ask-text"
                @keydown.esc="answer(false)"
            >
                <p id="ask-text" class="text-lg leading-snug">{{ asking.text }}</p>
                <div class="mt-5 flex flex-col gap-2">
                    <button ref="yesButton" type="button" class="btn-primary" :class="asking.danger ? 'bg-chili hover:bg-chili' : ''" @click="answer(true)">
                        {{ asking.yes }}
                    </button>
                    <button type="button" class="btn-quiet w-full border-2 border-rule" @click="answer(false)">{{ asking.no }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import { answer, asking } from '@/lib/ask';

const yesButton = ref(null);
let before = null;

watch(
    () => asking.open,
    async (open) => {
        if (open) {
            before = document.activeElement;
            await nextTick();
            yesButton.value?.focus();
        } else {
            before?.focus?.();
        }
    },
);
</script>
