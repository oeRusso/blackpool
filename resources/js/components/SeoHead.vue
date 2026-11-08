<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { regionalLocale, useI18n } from '@/lib/i18n';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        /** Absolute or root-relative image URL. Defaults to the site preview. */
        image?: string | null;
        type?: 'website' | 'article';
        /** Canonical path; defaults to the current path without query string. */
        path?: string;
    }>(),
    { image: null, type: 'website', path: undefined },
);

const page = usePage();
const { locale } = useI18n();

const absolute = (url: string): string =>
    /^https?:\/\//.test(url) ? url : `${page.props.appUrl}${url}`;

const canonical = computed(() =>
    absolute(props.path ?? page.url.split('?')[0]),
);
const image = computed(() => absolute(props.image ?? '/og-image.jpg'));
const ogLocale = computed(() => regionalLocale(locale.value).replace('-', '_'));
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <meta head-key="og:type" property="og:type" :content="type" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="page.props.name"
        />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="description"
        />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:image" property="og:image" :content="image" />
        <meta head-key="og:locale" property="og:locale" :content="ogLocale" />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        />
    </Head>
</template>
