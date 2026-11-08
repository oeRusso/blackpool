<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- BlackPool mark: a speech bubble holding three "pool" waves. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            d="M6 3h20a4 4 0 0 1 4 4v14a4 4 0 0 1-4 4H14.5L8.6 29.6A1 1 0 0 1 7 28.8V25H6a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"
            fill="currentColor"
            opacity=".22"
        />
        <path
            d="M7 10.5c1.5-1.4 3-1.4 4.5 0s3 1.4 4.5 0 3-1.4 4.5 0 3 1.4 4.5 0M7 15c1.5-1.4 3-1.4 4.5 0s3 1.4 4.5 0 3-1.4 4.5 0 3 1.4 4.5 0M7 19.5c1.5-1.4 3-1.4 4.5 0s3 1.4 4.5 0 3-1.4 4.5 0"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
        />
    </svg>
</template>
