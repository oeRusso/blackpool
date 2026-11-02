<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { LessonStatus } from '@/types';

defineProps<{ status: LessonStatus }>();
</script>

<template>
    <Badge
        :variant="status === 'published' ? 'default' : 'secondary'"
        class="capitalize"
    >
        {{ status }}
    </Badge>
</template>
