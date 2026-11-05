<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, Languages } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/lib/i18n';
import { update } from '@/routes/locale';

const page = usePage();
const { t, locale } = useI18n();
const locales = computed(() => Object.entries(page.props.locales ?? {}));

function switchTo(code: string): void {
    if (code === locale.value) {
        return;
    }

    router.put(
        update.url(),
        { locale: code },
        {
            preserveScroll: true,
            onSuccess: () => {
                document.documentElement.lang = code;
            },
        },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="gap-1.5"
                data-test="locale-switcher"
            >
                <Languages class="size-4" />
                <span class="sr-only">{{ t('Change language') }}:</span>
                <span class="uppercase">{{ locale }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-36">
            <DropdownMenuItem
                v-for="[code, name] in locales"
                :key="code"
                :lang="code"
                :data-test="`locale-${code}`"
                @select="switchTo(code)"
            >
                <Check
                    class="size-4"
                    :class="code === locale ? 'opacity-100' : 'opacity-0'"
                />
                {{ name }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
