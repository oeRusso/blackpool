<script setup lang="ts">
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{ placeholder?: string; delay?: number }>(),
    { placeholder: undefined, delay: 300 },
);

const emit = defineEmits<{ search: [value: string] }>();

const emitSearch = useDebounceFn(
    (value: string) => emit('search', value),
    props.delay,
);
</script>

<template>
    <div class="relative w-full sm:w-72">
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="model"
            type="search"
            :placeholder="placeholder ?? t('Search…')"
            :aria-label="placeholder ?? t('Search…')"
            class="pl-8"
            @update:model-value="emitSearch(String($event))"
        />
    </div>
</template>
