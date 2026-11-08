<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    FolderTree,
    LayoutGrid,
    Library,
    ShieldCheck,
    SquareChartGantt,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as catalog } from '@/routes/lessons';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as lessonsIndex } from '@/routes/admin/lessons';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const page = usePage();
const can = computed(() => page.props.auth.can);

const mainNavItems: NavItem[] = [
    {
        title: 'My learning',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Browse lessons',
        href: catalog(),
        icon: Library,
    },
];

const adminNavItems = computed<NavItem[]>(() => {
    const items: [boolean, NavItem][] = [
        [
            can.value.accessAdmin,
            {
                title: 'Overview',
                href: adminDashboard(),
                icon: SquareChartGantt,
            },
        ],
        [
            can.value.manageLessons,
            { title: 'Lessons', href: lessonsIndex(), icon: BookOpen },
        ],
        [
            can.value.manageCategories,
            { title: 'Categories', href: categoriesIndex(), icon: FolderTree },
        ],
        [
            can.value.manageUsers,
            { title: 'Users', href: usersIndex(), icon: Users },
        ],
        [
            can.value.manageRoles,
            { title: 'Roles', href: rolesIndex(), icon: ShieldCheck },
        ],
    ];

    return items.filter(([allowed]) => allowed).map(([, item]) => item);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Learning" />
            <NavMain
                v-if="adminNavItems.length > 0"
                :items="adminNavItems"
                label="Admin"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
