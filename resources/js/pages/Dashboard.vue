<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenCheck,
    Flame,
    GraduationCap,
    Heart,
    ListChecks,
    PlayCircle,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import LessonCard from '@/components/learn/LessonCard.vue';
import LevelBadge from '@/components/learn/LevelBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as catalog, show } from '@/routes/lessons';
import type { LessonCard as LessonCardType } from '@/types';

const props = defineProps<{
    stats: {
        completed: number;
        total: number;
        inProgress: number;
        quizzesPassed: number;
        completedThisWeek: number;
    };
    levels: { code: string; name: string; total: number; completed: number }[];
    continueLearning: LessonCardType[];
    favorites: LessonCardType[];
    recentAttempts: {
        id: number;
        lesson: { title: string; slug: string };
        score: number;
        total: number;
        percentage: number;
        created_at: string | null;
    }[];
    nextLesson: LessonCardType | null;
    isPremium: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My learning', href: dashboard() }] },
});

const overall = computed(() =>
    props.stats.total
        ? Math.round((props.stats.completed / props.stats.total) * 100)
        : 0,
);

const tiles = computed(() => [
    {
        label: 'Lessons completed',
        value: `${props.stats.completed}/${props.stats.total}`,
        icon: BookOpenCheck,
        test: 'completed',
    },
    {
        label: 'In progress',
        value: props.stats.inProgress,
        icon: PlayCircle,
        test: 'in-progress',
    },
    {
        label: 'Quizzes passed',
        value: props.stats.quizzesPassed,
        icon: ListChecks,
        test: 'quizzes',
    },
    {
        label: 'Completed this week',
        value: props.stats.completedThisWeek,
        icon: Flame,
        test: 'week',
    },
]);
</script>

<template>
    <Head title="My learning" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-end"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Welcome back,
                    {{ $page.props.auth.user.name.split(' ')[0] }} 👋
                </h1>
                <p class="text-sm text-muted-foreground">
                    You have completed {{ overall }}% of the course.
                </p>
            </div>
            <Button as-child
                ><Link :href="catalog()">Browse lessons <ArrowRight /></Link
            ></Button>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card v-for="tile in tiles" :key="tile.label" class="gap-2 py-4">
                <CardHeader
                    class="flex flex-row items-center justify-between px-4"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                        >{{ tile.label }}</CardTitle
                    >
                    <component
                        :is="tile.icon"
                        class="size-4 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent class="px-4">
                    <p
                        class="text-3xl font-semibold tabular-nums"
                        :data-test="`stat-${tile.test}`"
                    >
                        {{ tile.value }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <Card
                    v-if="nextLesson"
                    class="overflow-hidden"
                    data-test="next-lesson"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2"
                            ><GraduationCap class="size-5" /> Up next</CardTitle
                        >
                    </CardHeader>
                    <CardContent
                        class="flex flex-col gap-4 sm:flex-row sm:items-center"
                    >
                        <div class="flex-1">
                            <div class="mb-1 flex items-center gap-2">
                                <LevelBadge :code="nextLesson.level" />
                            </div>
                            <p class="text-lg font-semibold">
                                {{ nextLesson.title }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ nextLesson.summary }}
                            </p>
                        </div>
                        <Button as-child
                            ><Link :href="show(nextLesson.slug)"
                                >Start lesson</Link
                            ></Button
                        >
                    </CardContent>
                </Card>

                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Continue learning</h2>
                    <EmptyState
                        v-if="continueLearning.length === 0"
                        :icon="PlayCircle"
                        title="Nothing in progress"
                        description="Open any lesson to start tracking your progress."
                    />
                    <div v-else class="grid gap-4 sm:grid-cols-2">
                        <LessonCard
                            v-for="lesson in continueLearning"
                            :key="lesson.id"
                            :lesson="lesson"
                        />
                    </div>
                </section>

                <section class="space-y-3">
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <Heart class="size-4" /> Saved lessons
                    </h2>
                    <EmptyState
                        v-if="favorites.length === 0"
                        :icon="Heart"
                        title="No saved lessons yet"
                    />
                    <div
                        v-else
                        class="grid gap-4 sm:grid-cols-2"
                        data-test="favorites"
                    >
                        <LessonCard
                            v-for="lesson in favorites"
                            :key="lesson.id"
                            :lesson="lesson"
                        />
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>Progress by level</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <div
                            v-for="level in levels"
                            :key="level.code"
                            class="space-y-1.5"
                            :data-test="`level-progress-${level.code}`"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="flex items-center gap-2"
                                    ><LevelBadge :code="level.code" />
                                    {{ level.name }}</span
                                >
                                <span class="text-muted-foreground tabular-nums"
                                    >{{ level.completed }}/{{
                                        level.total
                                    }}</span
                                >
                            </div>
                            <Progress
                                :model-value="
                                    level.total
                                        ? (level.completed / level.total) * 100
                                        : 0
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle>Recent quizzes</CardTitle></CardHeader
                    >
                    <CardContent>
                        <p
                            v-if="recentAttempts.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Complete a lesson quiz to see your scores here.
                        </p>
                        <ul v-else class="divide-y text-sm">
                            <li
                                v-for="attempt in recentAttempts"
                                :key="attempt.id"
                                class="flex items-center justify-between gap-2 py-2"
                            >
                                <Link
                                    :href="show(attempt.lesson.slug)"
                                    class="truncate hover:underline"
                                    >{{ attempt.lesson.title }}</Link
                                >
                                <span
                                    class="shrink-0 tabular-nums"
                                    :class="
                                        attempt.percentage >= 70
                                            ? 'text-emerald-600'
                                            : 'text-amber-600'
                                    "
                                    >{{ attempt.score }}/{{
                                        attempt.total
                                    }}</span
                                >
                            </li>
                        </ul>
                        <p
                            v-if="recentAttempts[0]"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Last attempt
                            {{ formatDate(recentAttempts[0].created_at) }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="!isPremium" class="border-primary/30 bg-primary/5">
                    <CardHeader
                        ><CardTitle class="flex items-center gap-2"
                            ><Sparkles class="size-5" /> Go premium</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="text-sm text-muted-foreground">
                        Premium unlocks every lesson, worksheet and quiz. In
                        this demo, an administrator grants premium access from
                        the Users page.
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
