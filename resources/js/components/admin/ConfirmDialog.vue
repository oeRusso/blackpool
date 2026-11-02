<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel?: string;
        destructive?: boolean;
    }>(),
    { confirmLabel: 'Confirm', destructive: true },
);

const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <AlertDialog>
        <AlertDialogTrigger as-child>
            <slot />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{
                    description
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    :class="
                        buttonVariants({
                            variant: destructive ? 'destructive' : 'default',
                        })
                    "
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
