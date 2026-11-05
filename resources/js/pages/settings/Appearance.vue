<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { edit } from '@/routes/appearance';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Appearance settings')" />

    <h1 class="sr-only">{{ t('Appearance settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('Appearance settings')"
            :description="t('Update the appearance settings for your account')"
        />
        <AppearanceTabs />
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('Language')"
            :description="
                t(
                    'Choose the language of the interface. Lessons stay in English.',
                )
            "
        />
        <LocaleSwitcher />
    </div>
</template>
