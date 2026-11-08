<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ code: string }>();

// One hue per CEFR band: A (green), B (blue), C (purple).
const tone = computed(() => {
    switch (props.code[0]) {
        case 'A':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
        case 'B':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200';
        default:
            return 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
        :class="tone"
        >{{ code }}</span
    >
</template>
