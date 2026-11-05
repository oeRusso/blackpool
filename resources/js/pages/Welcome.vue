<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileText,
    Headphones,
    ListChecks,
    PlayCircle,
    TrendingUp,
} from '@lucide/vue';
import LessonCard from '@/components/learn/LessonCard.vue';
import LevelBadge from '@/components/learn/LevelBadge.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import { index as catalog } from '@/routes/lessons';
import type { LessonCard as LessonCardType } from '@/types';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

defineProps<{
    levels: {
        code: string;
        name: string;
        description: string | null;
        lessons_count: number;
    }[];
    featured: LessonCardType[];
    stats: { lessons: number; free: number; categories: number };
}>();

const steps = computed(() => [
    {
        icon: PlayCircle,
        title: t('Watch'),
        text: t('Short video lessons streamed in adaptive quality.'),
    },
    {
        icon: FileText,
        title: t('Practise'),
        text: t('Download the PDF worksheet for every lesson.'),
    },
    {
        icon: ListChecks,
        title: t('Check'),
        text: t('Pass the quiz to complete the lesson.'),
    },
    {
        icon: TrendingUp,
        title: t('Progress'),
        text: t('Track your progress from A1 to C2.'),
    },
]);
</script>

<template>
    <SeoHead
        :title="t('Learn English, level by level')"
        :description="
            t(
                'BlackPool is an English-learning platform with video lessons, worksheets and quizzes for every CEFR level from A1 to C2.',
            )
        "
    />

    <section class="relative overflow-hidden">
        <div
            class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,var(--color-primary)_0%,transparent_60%)] opacity-15"
        />
        <div
            class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:py-24 lg:grid-cols-2"
        >
            <div class="space-y-6">
                <span
                    class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-medium"
                >
                    <Headphones class="size-3.5 text-primary" />
                    {{
                        t(':count free lessons to get started', {
                            count: stats.free,
                        })
                    }}
                </span>
                <h1
                    class="text-4xl font-extrabold tracking-tight text-balance md:text-6xl"
                >
                    {{ t('Learn English,') }}
                    <span class="text-primary">{{ t('level by level.') }}</span>
                </h1>
                <p class="max-w-xl text-lg text-muted-foreground">
                    {{
                        t(
                            'Video lessons, printable worksheets and quick quizzes organised by the six CEFR levels. Know exactly where you are and what to learn next.',
                        )
                    }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button size="lg" as-child>
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            data-test="hero-cta"
                            >{{ t('Continue learning') }} <ArrowRight
                        /></Link>
                        <Link v-else :href="register()" data-test="hero-cta"
                            >{{ t('Start for free') }} <ArrowRight
                        /></Link>
                    </Button>
                    <Button size="lg" variant="outline" as-child>
                        <Link :href="catalog()">{{
                            t('Browse :count lessons', {
                                count: stats.lessons,
                            })
                        }}</Link>
                    </Button>
                </div>
            </div>
            <div class="relative">
                <img
                    src="/images/img-ingles.webp"
                    :alt="t('Students learning English together')"
                    class="aspect-[4/3] w-full rounded-2xl object-cover shadow-2xl ring-1 ring-border"
                    fetchpriority="high"
                />
                <div
                    class="absolute -bottom-5 -left-5 hidden rounded-xl border bg-background p-4 shadow-lg sm:block"
                >
                    <p class="text-sm text-muted-foreground">{{ t('From') }}</p>
                    <p class="text-2xl font-bold">A1 → C2</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-2xl font-bold tracking-tight md:text-3xl">
                {{ t('Find your level') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{
                    t(
                        'Based on the Common European Framework of Reference for Languages.',
                    )
                }}
            </p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="level in levels"
                    :key="level.code"
                    :href="catalog({ query: { level: level.code } })"
                    class="group rounded-xl border bg-background p-5 transition hover:border-primary hover:shadow-md"
                    :data-test="`home-level-${level.code}`"
                >
                    <div class="flex items-center justify-between">
                        <LevelBadge :code="level.code" />
                        <span class="text-xs text-muted-foreground">{{
                            t(':count lessons', {
                                count: level.lessons_count,
                            })
                        }}</span>
                    </div>
                    <p class="mt-3 font-semibold group-hover:text-primary">
                        {{ t(level.name) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t(level.description ?? '') }}
                    </p>
                </Link>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16">
        <h2 class="text-2xl font-bold tracking-tight md:text-3xl">
            {{ t('How it works') }}
        </h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="space-y-2"
            >
                <div
                    class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component :is="step.icon" class="size-5" />
                </div>
                <p class="font-semibold">{{ index + 1 }}. {{ step.title }}</p>
                <p class="text-sm text-muted-foreground">{{ step.text }}</p>
            </li>
        </ol>
    </section>

    <section v-if="featured.length" class="border-t bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight md:text-3xl">
                        {{ t('Free lessons') }}
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        {{ t('Create an account and start right away.') }}
                    </p>
                </div>
                <Button variant="ghost" as-child class="hidden sm:inline-flex">
                    <Link :href="catalog({ query: { access: 'free' } })"
                        >{{ t('See all') }} <ArrowRight
                    /></Link>
                </Button>
            </div>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <LessonCard
                    v-for="lesson in featured"
                    :key="lesson.id"
                    :lesson="lesson"
                />
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16">
        <div
            class="flex flex-col items-center gap-4 rounded-2xl bg-primary px-6 py-12 text-center text-primary-foreground"
        >
            <h2 class="text-2xl font-bold md:text-3xl">
                {{ t('Ready to speak with confidence?') }}
            </h2>
            <p class="max-w-xl opacity-90">
                {{
                    t(
                        'Join for free and unlock every A1–C2 free lesson, worksheet and quiz.',
                    )
                }}
            </p>
            <Button size="lg" variant="secondary" as-child>
                <Link
                    :href="$page.props.auth.user ? dashboard() : register()"
                    >{{ t('Get started') }}</Link
                >
            </Button>
        </div>
    </section>
</template>
