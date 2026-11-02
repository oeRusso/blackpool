<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-bold tracking-tight"
            >Black<span class="text-primary">Pool</span></span
        >
        <span class="truncate text-[11px] leading-tight text-muted-foreground"
            >English, level by level</span
        >
    </div>
</template>
