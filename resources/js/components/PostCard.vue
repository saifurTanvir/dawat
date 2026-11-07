<template>
    <article class="overflow-hidden rounded-[var(--radius-plate)] bg-plate shadow-[0_1px_0_var(--color-rule)]" :aria-label="post.title">
        <!-- Photos: swipe sideways when there is more than one -->
        <div class="relative">
            <div
                class="flex aspect-[4/3] snap-x snap-mandatory overflow-x-auto bg-leaf-soft [scrollbar-width:none]"
                tabindex="0"
                aria-label="খাবারের ছবি"
                @scroll.passive="onScroll"
            >
                <img
                    v-for="(img, i) in post.images"
                    :key="img.url"
                    :src="img.url"
                    :alt="i === 0 ? post.title : ''"
                    class="h-full w-full shrink-0 snap-center object-cover"
                    :loading="i === 0 ? 'eager' : 'lazy'"
                    decoding="async"
                />
            </div>
            <span class="absolute top-3 left-3 rounded-full bg-ink/75 px-3 py-0.5 text-sm text-white">
                {{ post.meal_slot_label }}
            </span>
            <span
                v-if="post.images.length > 1"
                class="absolute right-3 bottom-3 rounded-full bg-ink/75 px-2.5 py-0.5 text-sm text-white"
                aria-hidden="true"
            >
                {{ bn(activeImage + 1) }}/{{ bn(post.images.length) }}
            </span>
        </div>

        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-serif text-xl leading-snug font-bold">{{ post.title }}</h3>
                <p class="shrink-0 rounded-lg bg-turmeric-soft px-2.5 py-1 text-lg leading-none font-semibold">
                    {{ taka(post.price_paisa) }}<span class="text-sm font-normal text-muted">/প্লেট</span>
                </p>
            </div>

            <p class="mt-1 text-muted">
                <span :class="post.plates_left <= 1 ? 'font-semibold text-chili' : ''">
                    {{ bn(post.plates_left) }} প্লেট বাকি
                </span>
                · {{ clockText(post.available_until) }} পর্যন্ত
            </p>

            <p v-if="$slots.meta" class="mt-1 text-sm text-muted"><slot name="meta" /></p>

            <ul v-if="post.tags?.length" class="mt-3 flex flex-wrap gap-1.5" aria-label="খাবারের বৈশিষ্ট্য">
                <li
                    v-for="tag in post.tags"
                    :key="tag.id"
                    class="rounded-full px-2.5 py-0.5 text-sm"
                    :class="tagClass(tag.type)"
                >
                    {{ tag.name }}
                </li>
            </ul>

            <blockquote v-if="post.note" class="mt-3 border-l-4 border-leaf pl-3">
                <p>{{ post.note }}</p>
                <footer class="mt-0.5 text-sm text-muted">— {{ kitchenName || 'রাঁধুনির কথা' }}</footer>
            </blockquote>

            <div v-if="$slots.actions" class="mt-4">
                <slot name="actions" />
            </div>
        </div>
    </article>
</template>

<script setup>
import { ref } from 'vue';
import { bn } from '@/lib/bn';
import { clockText, taka } from '@/lib/format';

defineProps({
    post: { type: Object, required: true },
    kitchenName: { type: String, default: '' },
});

const activeImage = ref(0);

function onScroll(e) {
    const el = e.target;
    activeImage.value = Math.round(el.scrollLeft / el.clientWidth);
}

function tagClass(type) {
    switch (type) {
        case 'warning':
            return 'bg-chili/10 text-chili';
        case 'spice':
            return 'bg-chili/10 text-ink';
        case 'diet':
            return 'bg-leaf-soft text-leaf-deep';
        default:
            return 'bg-steel text-ink';
    }
}
</script>
