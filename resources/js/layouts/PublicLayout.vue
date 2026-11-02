<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, home, login, register } from '@/routes';
import { index as catalog } from '@/routes/lessons';

const page = usePage();
const user = computed(() => page.props.auth.user);
const mobileOpen = ref(false);
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="sticky top-0 z-40 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4"
            >
                <Link :href="home()" class="flex items-center gap-2">
                    <AppLogo />
                </Link>
                <nav
                    class="hidden items-center gap-1 text-sm md:flex"
                    aria-label="Main"
                >
                    <Button variant="ghost" as-child
                        ><Link :href="catalog()">Lessons</Link></Button
                    >
                    <template v-if="user">
                        <Button as-child data-test="nav-dashboard">
                            <Link :href="dashboard()"
                                ><LayoutDashboard /> My learning</Link
                            >
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child
                            ><Link :href="login()">Log in</Link></Button
                        >
                        <Button as-child
                            ><Link :href="register()"
                                >Start for free</Link
                            ></Button
                        >
                    </template>
                </nav>
                <Button
                    variant="ghost"
                    size="icon"
                    class="md:hidden"
                    aria-label="Toggle menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <Menu />
                </Button>
            </div>
            <nav
                v-if="mobileOpen"
                class="grid gap-1 border-t px-4 py-3 md:hidden"
                aria-label="Mobile"
            >
                <Link
                    :href="catalog()"
                    class="rounded-md px-3 py-2 hover:bg-muted"
                    >Lessons</Link
                >
                <Link
                    v-if="user"
                    :href="dashboard()"
                    class="rounded-md px-3 py-2 hover:bg-muted"
                    >My learning</Link
                >
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-3 py-2 hover:bg-muted"
                        >Log in</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-md px-3 py-2 hover:bg-muted"
                        >Start for free</Link
                    >
                </template>
            </nav>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row"
            >
                <p>
                    © {{ year }} BlackPool · Learn English, one level at a time.
                </p>
                <nav class="flex gap-4" aria-label="Footer">
                    <Link :href="catalog()" class="hover:text-foreground"
                        >Lessons</Link
                    >
                    <a
                        v-if="page.props.repositoryUrl"
                        :href="String(page.props.repositoryUrl)"
                        class="hover:text-foreground"
                        rel="noopener"
                        target="_blank"
                        >Source code</a
                    >
                </nav>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
