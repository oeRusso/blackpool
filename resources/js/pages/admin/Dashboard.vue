<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FilePen,
    FolderTree,
    GraduationCap,
    Sparkles,
    SquareCheckBig,
} from '@lucide/vue';
import { computed } from 'vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { create, edit } from '@/routes/admin/lessons';
import type { LessonStatus } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t, locale } = useI18n();

const props = defineProps<{
    stats: {
        lessons: number;
        published: number;
        drafts: number;
        categories: number;
        students: number;
        premium: number;
    };
    lessonsByLevel: { code: string; name: string; count: number }[];
    recentLessons: {
        id: number;
        title: string;
        level: string;
        status: LessonStatus;
        updated_at: string | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: dashboard() }],
    },
});

const tiles = computed(() => [
    {
        key: 'lessons',
        label: t('Lessons'),
        value: props.stats.lessons,
        icon: BookOpen,
    },
    {
        key: 'published',
        label: t('Published'),
        value: props.stats.published,
        icon: SquareCheckBig,
    },
    {
        key: 'drafts',
        label: t('Drafts'),
        value: props.stats.drafts,
        icon: FilePen,
    },
    {
        key: 'categories',
        label: t('Categories'),
        value: props.stats.categories,
        icon: FolderTree,
    },
    {
        key: 'students',
        label: t('Students'),
        value: props.stats.students,
        icon: GraduationCap,
    },
    {
        key: 'premium',
        label: t('Premium'),
        value: props.stats.premium,
        icon: Sparkles,
    },
]);

const maxPerLevel = computed(() =>
    Math.max(1, ...props.lessonsByLevel.map((level) => level.count)),
);
</script>

<template>
    <Head :title="t('Admin dashboard')" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ t('Overview') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('Content and learners at a glance.') }}
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">{{ t('New lesson') }}</Link>
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <Card v-for="tile in tiles" :key="tile.key" class="gap-2 py-4">
                <CardHeader
                    class="flex flex-row items-center justify-between px-4"
                >
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ tile.label }}
                    </CardTitle>
                    <component
                        :is="tile.icon"
                        class="size-4 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent class="px-4">
                    <p
                        class="text-3xl font-semibold tabular-nums"
                        :data-test="`stat-${tile.key}`"
                    >
                        {{ tile.value }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Lessons per CEFR level') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="level in lessonsByLevel"
                        :key="level.code"
                        class="grid grid-cols-[3rem_1fr_2.5rem] items-center gap-3 text-sm"
                    >
                        <span class="font-semibold">{{ level.code }}</span>
                        <div
                            class="h-2.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${(level.count / maxPerLevel) * 100}%`,
                                }"
                            />
                        </div>
                        <span class="text-right tabular-nums">{{
                            level.count
                        }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('Recently updated') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="lesson in recentLessons"
                            :key="lesson.id"
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="edit(lesson.id)"
                                    class="truncate font-medium hover:underline"
                                    >{{ lesson.title }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ lesson.level }} ·
                                    {{ formatDate(lesson.updated_at, locale) }}
                                </p>
                            </div>
                            <StatusBadge :status="lesson.status" />
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
