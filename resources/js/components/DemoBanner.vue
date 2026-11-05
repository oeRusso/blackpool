<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import { computed } from 'vue';
import { login } from '@/routes';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const page = usePage();
const demo = computed(() => page.props.demo);
</script>

<template>
    <div
        v-if="demo"
        class="flex items-center justify-center gap-2 bg-amber-100 px-4 py-1.5 text-center text-xs text-amber-950 dark:bg-amber-950 dark:text-amber-100"
        role="note"
        data-test="demo-banner"
    >
        <FlaskConical class="size-3.5 shrink-0" />
        <span
            >{{ t('Public demo — content resets every night.') }}
            <Link
                v-if="!page.props.auth.user"
                :href="login()"
                class="font-semibold underline underline-offset-2"
                >{{ t('Try it with a demo account') }}</Link
            ></span
        >
    </div>
</template>
