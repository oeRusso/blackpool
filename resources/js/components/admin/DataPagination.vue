<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{
    paginator: Paginated<unknown>;
}>();

const pages = computed(() => props.paginator.links.slice(1, -1));
const previous = computed(() => props.paginator.links[0]);
const next = computed(
    () => props.paginator.links[props.paginator.links.length - 1],
);
</script>

<template>
    <nav
        v-if="paginator.last_page > 1 || paginator.total > 0"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
        :aria-label="t('Pagination')"
    >
        <p class="text-sm text-muted-foreground">
            {{
                t('Showing :from–:to of :total', {
                    from: paginator.from ?? 0,
                    to: paginator.to ?? 0,
                    total: paginator.total,
                })
            }}
        </p>
        <div v-if="paginator.last_page > 1" class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                :disabled="!previous?.url"
                as-child
            >
                <Link
                    v-if="previous?.url"
                    :href="previous.url"
                    preserve-scroll
                    :aria-label="t('Previous page')"
                >
                    <ChevronLeft />
                </Link>
                <span v-else aria-hidden="true"><ChevronLeft /></span>
            </Button>
            <template v-for="link in pages" :key="link.label">
                <Button
                    v-if="link.url"
                    :variant="link.active ? 'default' : 'ghost'"
                    size="icon"
                    as-child
                >
                    <Link
                        :href="link.url"
                        preserve-scroll
                        :aria-current="link.active ? 'page' : undefined"
                        >{{ link.label }}</Link
                    >
                </Button>
                <span v-else class="px-2 text-muted-foreground">…</span>
            </template>
            <Button
                variant="outline"
                size="icon"
                :disabled="!next?.url"
                as-child
            >
                <Link
                    v-if="next?.url"
                    :href="next.url"
                    preserve-scroll
                    :aria-label="t('Next page')"
                >
                    <ChevronRight />
                </Link>
                <span v-else aria-hidden="true"><ChevronRight /></span>
            </Button>
        </div>
    </nav>
</template>
