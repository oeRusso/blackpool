<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Trash2 } from '@lucide/vue';
import { reactive } from 'vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataPagination from '@/components/admin/DataPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import SearchInput from '@/components/admin/SearchInput.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/lessons';
import type { AdminLessonRow, Level, Paginated } from '@/types';

const props = defineProps<{
    lessons: Paginated<AdminLessonRow>;
    filters: { search: string; level: number | null; status: string | null };
    levels: Level[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Lessons', href: index() },
        ],
    },
});

const filters = reactive({
    search: props.filters.search,
    level: props.filters.level ?? '',
    status: props.filters.status ?? '',
});

function apply(): void {
    const query = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );

    router.get(index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function destroy(lesson: AdminLessonRow): void {
    router.delete(LessonController.destroy.url(lesson.id));
}
</script>

<template>
    <Head title="Lessons" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Lessons"
            description="Create, publish and organise the course content."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()" data-test="create-lesson"
                        ><Plus /> New lesson</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-2 sm:flex-row">
            <SearchInput
                v-model="filters.search"
                placeholder="Search lessons…"
                @search="apply"
            />
            <NativeSelect
                v-model="filters.level"
                class="sm:w-44"
                aria-label="Filter by level"
                @change="apply"
            >
                <option value="">All levels</option>
                <option
                    v-for="level in levels"
                    :key="level.id"
                    :value="level.id"
                >
                    {{ level.code }} · {{ level.name }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="filters.status"
                class="sm:w-40"
                aria-label="Filter by status"
                @change="apply"
            >
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </NativeSelect>
        </div>

        <EmptyState
            v-if="lessons.data.length === 0"
            :icon="BookOpen"
            title="No lessons match your filters"
        />

        <div v-else class="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead>Level</TableHead>
                        <TableHead class="hidden lg:table-cell"
                            >Categories</TableHead
                        >
                        <TableHead>Status</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Updated</TableHead
                        >
                        <TableHead class="w-24"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="lesson in lessons.data"
                        :key="lesson.id"
                        :data-test="`lesson-row-${lesson.slug}`"
                    >
                        <TableCell class="max-w-xs">
                            <Link
                                :href="edit(lesson.id)"
                                class="font-medium hover:underline"
                                >{{ lesson.title }}</Link
                            >
                            <Badge
                                v-if="lesson.is_free"
                                variant="outline"
                                class="ml-2"
                                >Free</Badge
                            >
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{
                                lesson.level
                            }}</Badge>
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground lg:table-cell"
                            >{{ lesson.categories.join(', ') }}</TableCell
                        >
                        <TableCell
                            ><StatusBadge :status="lesson.status"
                        /></TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground md:table-cell"
                            >{{ formatDate(lesson.updated_at) }}</TableCell
                        >
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                    :aria-label="`Edit ${lesson.title}`"
                                >
                                    <Link :href="edit(lesson.id)"
                                        ><Pencil
                                    /></Link>
                                </Button>
                                <ConfirmDialog
                                    :title="`Delete “${lesson.title}”?`"
                                    description="The lesson will be moved to the trash and hidden from students."
                                    confirm-label="Delete"
                                    @confirm="destroy(lesson)"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive"
                                        :aria-label="`Delete ${lesson.title}`"
                                    >
                                        <Trash2 />
                                    </Button>
                                </ConfirmDialog>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <DataPagination :paginator="lessons" />
    </div>
</template>
