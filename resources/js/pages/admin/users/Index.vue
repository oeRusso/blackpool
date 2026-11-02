<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShieldCheck, Users } from '@lucide/vue';
import { reactive, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import DataPagination from '@/components/admin/DataPagination.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import SearchInput from '@/components/admin/SearchInput.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { index } from '@/routes/admin/users';
import type { AdminUserRow, Option, Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<AdminUserRow>;
    filters: { search: string; role: string | null };
    roles: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});

const filters = reactive({
    search: props.filters.search,
    role: props.filters.role ?? '',
});

const editing = ref<AdminUserRow | null>(null);
const selectedRoles = ref<string[]>([]);
const saving = ref(false);

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

function openRoles(user: AdminUserRow): void {
    editing.value = user;
    selectedRoles.value = [...user.roles];
}

function saveRoles(): void {
    if (!editing.value) {
        return;
    }

    router.put(
        UserController.updateRoles.url(editing.value.id),
        { roles: selectedRoles.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (editing.value = null),
        },
    );
}

const roleVariant = (role: string) =>
    role === 'admin' ? 'default' : role === 'premium' ? 'outline' : 'secondary';
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Grant premium access or staff roles."
        />

        <div class="flex flex-col gap-2 sm:flex-row">
            <SearchInput
                v-model="filters.search"
                placeholder="Search by name or email…"
                @search="apply"
            />
            <NativeSelect
                v-model="filters.role"
                class="sm:w-48"
                aria-label="Filter by role"
                @change="apply"
            >
                <option value="">All roles</option>
                <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                >
                    {{ role.label }}
                </option>
            </NativeSelect>
        </div>

        <EmptyState
            v-if="users.data.length === 0"
            :icon="Users"
            title="No users found"
        />

        <div v-else class="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Joined</TableHead
                        >
                        <TableHead class="w-32"
                            ><span class="sr-only">Actions</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="user in users.data"
                        :key="user.id"
                        :data-test="`user-row-${user.email}`"
                    >
                        <TableCell>
                            <p class="font-medium">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    :variant="roleVariant(role)"
                                    class="capitalize"
                                    >{{ role }}</Badge
                                >
                                <span
                                    v-if="user.roles.length === 0"
                                    class="text-xs text-muted-foreground"
                                    >No role</span
                                >
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground md:table-cell"
                            >{{ formatDate(user.created_at) }}</TableCell
                        >
                        <TableCell class="text-right">
                            <Button
                                v-if="!user.is_current"
                                variant="outline"
                                size="sm"
                                @click="openRoles(user)"
                            >
                                <ShieldCheck /> Roles
                            </Button>
                            <span v-else class="text-xs text-muted-foreground"
                                >You</span
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <DataPagination :paginator="users" />
    </div>

    <Dialog
        :open="editing !== null"
        @update:open="(open) => !open && (editing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Roles for {{ editing?.name }}</DialogTitle>
                <DialogDescription
                    >Premium students can watch every lesson. Editors and admins
                    can access the admin area.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-2">
                <label
                    v-for="role in roles"
                    :key="role.value"
                    class="flex items-center gap-3 rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-primary/5"
                >
                    <input
                        v-model="selectedRoles"
                        type="checkbox"
                        :value="role.value"
                        class="accent-primary"
                        :data-test="`role-${role.value}`"
                    />
                    {{ role.label }}
                </label>
            </div>
            <DialogFooter>
                <Button
                    :disabled="saving"
                    data-test="save-roles"
                    @click="saveRoles"
                    >Save roles</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
