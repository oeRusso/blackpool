import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import es from '../../../lang/es.json';

/**
 * Interface translations. English strings are the keys (as in Laravel's
 * `__()`), so English needs no catalog and a missing translation falls back to
 * readable English. `lang/es.json` is shared with the backend.
 *
 * The locale is read from the Inertia page props on every call instead of
 * being stored in a global, so concurrent SSR renders cannot leak it.
 */
type Catalog = Record<string, string>;

const catalogs: Record<string, Catalog> = { es };

export type Replacements = Record<string, string | number>;

export type Translate = (key: string, replacements?: Replacements) => string;

export function translate(
    locale: string,
    key: string,
    replacements: Replacements = {},
): string {
    let message = catalogs[locale]?.[key] ?? key;

    // Longest placeholder first so `:total` is not clobbered by `:to`.
    const entries = Object.entries(replacements).sort(
        ([a], [b]) => b.length - a.length,
    );

    for (const [name, value] of entries) {
        message = message.replaceAll(`:${name}`, String(value));
    }

    return message;
}

export function hasTranslation(locale: string, key: string): boolean {
    return locale === 'en' || key in (catalogs[locale] ?? {});
}

/** BCP 47 tag for Intl formatters and Open Graph, e.g. `es` → `es-ES`. */
export function regionalLocale(locale: string): string {
    return locale === 'es' ? 'es-ES' : 'en-US';
}

export function useI18n(): {
    t: Translate;
    locale: ComputedRef<string>;
} {
    const page = usePage();
    const locale = computed(() => page.props?.locale ?? 'en');

    const t: Translate = (key, replacements) =>
        translate(locale.value, key, replacements);

    return { t, locale };
}
