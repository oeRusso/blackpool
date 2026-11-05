<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { FolderTree, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataPagination from '@/components/admin/DataPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import SearchInput from '@/components/admin/SearchInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/categories';
import type { Category, Paginated } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{
    categories: Paginated<Category>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Categories', href: index() },
        ],
    },
});

const search = ref(props.filters.search);
const dialogOpen = ref(false);
const editing = ref<Category | null>(null);

function onSearch(value: string): void {
    router.get(index.url(), value ? { search: value } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(category: Category): void {
    editing.value = category;
    dialogOpen.value = true;
}

function destroy(category: Category): void {
    router.delete(CategoryController.destroy.url(category.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('Categories')" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="t('Categories')"
            :description="t('Group lessons by skill or topic.')"
        >
            <template #actions>
                <SearchInput
                    v-model="search"
                    :placeholder="t('Search categories…')"
                    @search="onSearch"
                />
                <Button data-test="create-category" @click="openCreate">
                    <Plus /> {{ t('New category') }}
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="categories.data.length === 0"
            :icon="FolderTree"
            :title="t('No categories found')"
            :description="
                t('Create your first category to start organising lessons.')
            "
        />

        <div v-else class="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('Name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{
                            t('Description')
                        }}</TableHead>
                        <TableHead class="text-right">{{
                            t('Lessons')
                        }}</TableHead>
                        <TableHead class="w-24 text-right"
                            ><span class="sr-only">{{
                                t('Actions')
                            }}</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="category in categories.data"
                        :key="category.id"
                        :data-test="`category-row-${category.slug}`"
                    >
                        <TableCell class="font-medium">{{
                            category.name
                        }}</TableCell>
                        <TableCell
                            class="hidden max-w-md truncate text-muted-foreground md:table-cell"
                            >{{ category.description ?? '—' }}</TableCell
                        >
                        <TableCell class="text-right tabular-nums">{{
                            category.lessons_count
                        }}</TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="
                                        t('Edit :name', { name: category.name })
                                    "
                                    @click="openEdit(category)"
                                >
                                    <Pencil />
                                </Button>
                                <ConfirmDialog
                                    :title="
                                        t('Delete “:name”?', {
                                            name: category.name,
                                        })
                                    "
                                    :description="
                                        t(
                                            'This cannot be undone. Categories that still have lessons cannot be deleted.',
                                        )
                                    "
                                    :confirm-label="t('Delete')"
                                    @confirm="destroy(category)"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive"
                                        :aria-label="
                                            t('Delete :name', {
                                                name: category.name,
                                            })
                                        "
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

        <DataPagination :paginator="categories" />
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="
                    editing
                        ? CategoryController.update.form(editing.id)
                        : CategoryController.store.form()
                "
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="dialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? t('Edit category') : t('New category')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('Category names must be unique.')
                    }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="category-name">{{ t('Name') }}</Label>
                    <Input
                        id="category-name"
                        name="name"
                        :default-value="editing?.name"
                        required
                        maxlength="100"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="category-description">{{
                        t('Description')
                    }}</Label>
                    <Textarea
                        id="category-description"
                        name="description"
                        :default-value="editing?.description ?? ''"
                        maxlength="255"
                        rows="3"
                    />
                    <InputError :message="errors.description" />
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="save-category"
                        >{{ t('Save') }}</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
