<template>
    <header class="flex items-center justify-between px-4 pt-5 pb-3">
        <RouterLink to="/" class="group flex items-baseline gap-2" aria-label="দাওয়াত — হোম">
            <span class="font-serif text-3xl leading-none font-bold text-leaf">দাওয়াত</span>
            <span class="text-sm text-muted">পাড়ার ঘরের খাবার</span>
        </RouterLink>
        <slot />
    </header>
</template>
