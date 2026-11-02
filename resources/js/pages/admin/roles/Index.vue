<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { reactive } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import PageHeader from '@/components/admin/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/roles';
import type { AdminRole, Option } from '@/types';

const props = defineProps<{
    roles: AdminRole[];
    permissions: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Roles', href: index() },
        ],
    },
});

const selected = reactive<Record<number, string[]>>(
    Object.fromEntries(
        props.roles.map((role) => [role.id, [...role.permissions]]),
    ),
);

function save(role: AdminRole): void {
    router.put(
        RoleController.update.url(role.id),
        { permissions: selected[role.id] },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Roles & permissions" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Roles & permissions"
            description="Decide what each role can do. The administrator role always has every permission."
        />

        <div class="grid gap-4 md:grid-cols-2">
            <Card
                v-for="role in roles"
                :key="role.id"
                :data-test="`role-card-${role.name}`"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ role.label }}
                        <Lock
                            v-if="role.locked"
                            class="size-4 text-muted-foreground"
                        />
                    </CardTitle>
                    <CardDescription>
                        <Badge variant="secondary"
                            >{{ role.users_count }} users</Badge
                        >
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <label
                        v-for="permission in permissions"
                        :key="permission.value"
                        class="flex items-center gap-3 text-sm"
                    >
                        <input
                            v-model="selected[role.id]"
                            type="checkbox"
                            :value="permission.value"
                            :disabled="role.locked"
                            class="accent-primary"
                        />
                        {{ permission.label }}
                    </label>
                </CardContent>
                <CardFooter v-if="!role.locked">
                    <Button
                        size="sm"
                        :data-test="`save-role-${role.name}`"
                        @click="save(role)"
                        >Save</Button
                    >
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
