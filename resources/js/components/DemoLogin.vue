<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { GraduationCap, PenLine, ShieldCheck, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/login';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const page = usePage();
const demo = computed(() => page.props.demo);
const loading = ref<string | null>(null);

const roles = [
    { role: 'admin', label: 'Administrator', icon: ShieldCheck },
    { role: 'editor', label: 'Editor', icon: PenLine },
    { role: 'premium', label: 'Premium', icon: Sparkles },
    { role: 'student', label: 'Student', icon: GraduationCap },
] as const;

function loginAs(role: string): void {
    const email = demo.value?.accounts[role];

    if (!email || !demo.value) {
        return;
    }

    router.post(
        store.url(),
        { email, password: demo.value.password },
        {
            onStart: () => (loading.value = role),
            onFinish: () => (loading.value = null),
        },
    );
}
</script>

<template>
    <div
        v-if="demo"
        class="space-y-3 rounded-lg border border-dashed p-4"
        data-test="demo-login"
    >
        <p class="text-center text-sm font-medium">
            {{ t('One-click demo login') }}
        </p>
        <div class="grid grid-cols-2 gap-2">
            <Button
                v-for="item in roles"
                :key="item.role"
                type="button"
                variant="outline"
                size="sm"
                :disabled="loading !== null"
                :data-test="`demo-login-${item.role}`"
                @click="loginAs(item.role)"
            >
                <component :is="item.icon" /> {{ t(item.label) }}
            </Button>
        </div>
        <p class="text-center text-xs text-muted-foreground">
            {{ t('Password for every demo account:') }}
            <code class="font-mono">{{ demo.password }}</code>
        </p>
    </div>
</template>
