<template>
    <ImpersonationBanner />
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <AppHeader />
        <main class="flex-1 px-4 pb-10">
            <RouterView />
        </main>
        <JoinBanner />
    </div>
</template>

<script setup>
import AppHeader from './components/AppHeader.vue';
import ImpersonationBanner from './components/ImpersonationBanner.vue';
import JoinBanner from './components/JoinBanner.vue';
</script>
