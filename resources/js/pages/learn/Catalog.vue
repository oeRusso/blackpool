<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { SearchX } from '@lucide/vue';
import { reactive } from 'vue';
import DataPagination from '@/components/admin/DataPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import SearchInput from '@/components/admin/SearchInput.vue';
import LessonCard from '@/components/learn/LessonCard.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/lessons';
import type { LessonCard as LessonCardType, Level, Paginated } from '@/types';

const props = defineProps<{
    lessons: Paginated<LessonCardType>;
    filters: {
        q: string;
        level: string | null;
        category: string | null;
        access: string | null;
    };
    levels: Level[];
    categories: { id: number; name: string; slug: string }[];
}>();

const filters = reactive({
    q: props.filters.q,
    level: props.filters.level ?? '',
    category: props.filters.category ?? '',
    access: props.filters.access ?? '',
});

function apply(): void {
    router.get(
        index.url(),
        Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== ''),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function selectLevel(code: string): void {
    filters.level = filters.level === code ? '' : code;
    apply();
}

function reset(): void {
    Object.assign(filters, { q: '', level: '', category: '', access: '' });
    apply();
}
</script>

<template>
    <Head title="Lessons">
        <meta
            name="description"
            content="Browse English lessons for every CEFR level, from A1 beginner to C2 proficient."
        />
    </Head>

    <section class="border-b bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-10">
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                Lessons
            </h1>
            <p class="mt-2 max-w-2xl text-muted-foreground">
                Video lessons, worksheets and quizzes for every level of the
                Common European Framework.
            </p>
            <div
                class="mt-6 flex flex-wrap gap-2"
                role="group"
                aria-label="Filter by level"
            >
                <Button
                    v-for="level in levels"
                    :key="level.code"
                    size="sm"
                    :variant="
                        filters.level === level.code ? 'default' : 'outline'
                    "
                    :aria-pressed="filters.level === level.code"
                    :title="level.description ?? undefined"
                    :data-test="`level-${level.code}`"
                    @click="selectLevel(level.code)"
                >
                    <span class="font-semibold">{{ level.code }}</span>
                    <span class="hidden sm:inline">{{ level.name }}</span>
                </Button>
            </div>
        </div>
    </section>

    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8">
        <div class="flex flex-col gap-2 md:flex-row md:items-center">
            <SearchInput
                v-model="filters.q"
                placeholder="Search lessons…"
                @search="apply"
            />
            <NativeSelect
                v-model="filters.category"
                class="md:w-52"
                aria-label="Category"
                @change="apply"
            >
                <option value="">All categories</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.slug"
                >
                    {{ category.name }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="filters.access"
                class="md:w-40"
                aria-label="Access"
                @change="apply"
            >
                <option value="">Free & premium</option>
                <option value="free">Free only</option>
                <option value="premium">Premium only</option>
            </NativeSelect>
            <p class="text-sm text-muted-foreground md:ml-auto">
                {{ lessons.total }} lessons
            </p>
        </div>

        <EmptyState
            v-if="lessons.data.length === 0"
            :icon="SearchX"
            title="No lessons match your search"
            description="Try another level or category."
        >
            <Button variant="outline" size="sm" class="mt-2" @click="reset"
                >Clear filters</Button
            >
        </EmptyState>

        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <LessonCard
                v-for="lesson in lessons.data"
                :key="lesson.id"
                :lesson="lesson"
            />
        </div>

        <DataPagination :paginator="lessons" />
    </div>
</template>
