<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import DemoBanner from '@/components/DemoBanner.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, home, login, register, sitemap } from '@/routes';
import { index as catalog } from '@/routes/lessons';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth.user);
const mobileOpen = ref(false);
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <DemoBanner />
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
                    :aria-label="t('Main')"
                >
                    <Button variant="ghost" as-child
                        ><Link :href="catalog()">{{
                            t('Lessons')
                        }}</Link></Button
                    >
                    <template v-if="user">
                        <Button as-child data-test="nav-dashboard">
                            <Link :href="dashboard()"
                                ><LayoutDashboard />
                                {{ t('My learning') }}</Link
                            >
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child
                            ><Link :href="login()">{{
                                t('Log in')
                            }}</Link></Button
                        >
                        <Button as-child
                            ><Link :href="register()">{{
                                t('Start for free')
                            }}</Link></Button
                        >
                    </template>
                    <LocaleSwitcher />
                </nav>
                <div class="flex items-center gap-1 md:hidden">
                    <LocaleSwitcher />
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="t('Toggle menu')"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Menu />
                    </Button>
                </div>
            </div>
            <nav
                v-if="mobileOpen"
                class="grid gap-1 border-t px-4 py-3 md:hidden"
                :aria-label="t('Mobile')"
            >
                <Link
                    :href="catalog()"
                    class="rounded-md px-3 py-2 hover:bg-muted"
                    >{{ t('Lessons') }}</Link
                >
                <Link
                    v-if="user"
                    :href="dashboard()"
                    class="rounded-md px-3 py-2 hover:bg-muted"
                    >{{ t('My learning') }}</Link
                >
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-3 py-2 hover:bg-muted"
                        >{{ t('Log in') }}</Link
                    >
                    <Link
                        :href="register()"
                        class="rounded-md px-3 py-2 hover:bg-muted"
                        >{{ t('Start for free') }}</Link
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
                    © {{ year }} BlackPool ·
                    {{ t('Learn English, one level at a time.') }}
                </p>
                <nav class="flex gap-4" :aria-label="t('Footer')">
                    <Link :href="catalog()" class="hover:text-foreground">{{
                        t('Lessons')
                    }}</Link>
                    <a :href="sitemap.url()" class="hover:text-foreground">{{
                        t('Sitemap')
                    }}</a>
                    <a
                        v-if="page.props.repositoryUrl"
                        :href="String(page.props.repositoryUrl)"
                        class="hover:text-foreground"
                        rel="noopener"
                        target="_blank"
                        >{{ t('Source code') }}</a
                    >
                </nav>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
