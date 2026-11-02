<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { index as catalog } from '@/routes/lessons';

const props = defineProps<{ status: number }>();

const content = computed(
    () =>
        ({
            403: {
                title: 'Access denied',
                text: 'You do not have permission to view this page.',
            },
            404: {
                title: 'Page not found',
                text: 'The page you are looking for does not exist or has moved.',
            },
            419: {
                title: 'Page expired',
                text: 'Your session expired. Please refresh and try again.',
            },
            429: {
                title: 'Too many requests',
                text: 'Please slow down and try again in a minute.',
            },
            500: {
                title: 'Something went wrong',
                text: 'An unexpected error occurred. We have been notified.',
            },
            503: {
                title: 'Back soon',
                text: 'We are performing maintenance. Please check back shortly.',
            },
        })[props.status] ?? {
            title: 'Error',
            text: 'An unexpected error occurred.',
        },
);
</script>

<template>
    <Head :title="content.title" />
    <section
        class="mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center gap-4 px-4 text-center"
        data-test="error-page"
    >
        <p class="text-7xl font-black text-primary/30">{{ status }}</p>
        <h1 class="text-2xl font-bold">{{ content.title }}</h1>
        <p class="text-muted-foreground">{{ content.text }}</p>
        <div class="flex gap-2">
            <Button as-child><Link :href="home()">Go home</Link></Button>
            <Button variant="outline" as-child
                ><Link :href="catalog()">Browse lessons</Link></Button
            >
        </div>
    </section>
</template>
