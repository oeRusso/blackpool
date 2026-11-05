<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { LessonStatus } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

defineProps<{ status: LessonStatus }>();
</script>

<template>
    <Badge :variant="status === 'published' ? 'default' : 'secondary'">
        {{ status === 'published' ? t('Published') : t('Draft') }}
    </Badge>
</template>
