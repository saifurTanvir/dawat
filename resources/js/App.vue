<template>
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <AppHeader />
        <main class="flex-1 px-4 pb-10">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import AppHeader from './components/AppHeader.vue';
</script>
