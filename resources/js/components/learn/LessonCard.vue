<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, Clock, Lock, PlayCircle } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LevelBadge from '@/components/learn/LevelBadge.vue';
import { formatDuration } from '@/lib/format';
import { show } from '@/routes/lessons';
import type { LessonCard } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{ lesson: LessonCard; locked?: boolean }>();

// Lessons without a cover get a gradient in their CEFR band colour.
const placeholderTone = computed(
    () =>
        ({
            A: 'from-emerald-500 to-teal-700',
            B: 'from-sky-500 to-blue-800',
            C: 'from-violet-500 to-indigo-800',
        })[props.lesson.level[0]] ?? 'from-slate-500 to-slate-800',
);
</script>

<template>
    <Link
        :href="show(lesson.slug)"
        class="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        :data-test="`lesson-card-${lesson.slug}`"
    >
        <div class="relative aspect-video overflow-hidden bg-muted">
            <img
                v-if="lesson.image_url"
                :src="lesson.image_url"
                :alt="''"
                loading="lazy"
                class="size-full object-cover transition duration-300 group-hover:scale-105"
            />
            <div
                v-else
                class="relative flex size-full items-end overflow-hidden bg-gradient-to-br p-4"
                :class="placeholderTone"
            >
                <AppLogoIcon
                    class="absolute -top-6 -right-6 size-40 text-white/15"
                />
                <span
                    class="text-5xl font-black tracking-tight text-white/90 drop-shadow-sm"
                    >{{ lesson.level }}</span
                >
            </div>
            <div class="absolute top-2 left-2 flex gap-1">
                <LevelBadge :code="lesson.level" />
                <span
                    v-if="lesson.is_free"
                    class="rounded-md bg-background/90 px-2 py-0.5 text-xs font-semibold"
                    >{{ t('Free') }}</span
                >
            </div>
            <span
                v-if="lesson.completed"
                class="absolute top-2 right-2 rounded-full bg-emerald-600 p-1 text-white"
                :aria-label="t('Completed')"
                ><CircleCheck class="size-4"
            /></span>
            <span
                v-else-if="locked || !lesson.is_free"
                class="absolute top-2 right-2 rounded-full bg-background/90 p-1"
                :aria-label="t('Premium lesson')"
                ><Lock class="size-4"
            /></span>
        </div>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <h3 class="leading-snug font-semibold group-hover:underline">
                {{ lesson.title }}
            </h3>
            <p class="line-clamp-2 text-sm text-muted-foreground">
                {{ lesson.summary }}
            </p>
            <div
                class="mt-auto flex items-center gap-3 pt-2 text-xs text-muted-foreground"
            >
                <span
                    v-if="lesson.duration_seconds"
                    class="flex items-center gap-1"
                    ><Clock class="size-3.5" />{{
                        formatDuration(lesson.duration_seconds)
                    }}</span
                >
                <span v-if="lesson.has_video" class="flex items-center gap-1"
                    ><PlayCircle class="size-3.5" /> {{ t('Video') }}</span
                >
                <span v-if="lesson.categories?.length" class="truncate">{{
                    lesson.categories.map((name) => t(name)).join(' · ')
                }}</span>
            </div>
        </div>
    </Link>
</template>
