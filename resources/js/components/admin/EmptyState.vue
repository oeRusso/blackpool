<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon?: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-10 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="size-8 text-muted-foreground"
        />
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
