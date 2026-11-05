import { describe, expect, it } from 'vitest';
import es from '../../../lang/es.json';
import { hasTranslation, regionalLocale, translate } from '@/lib/i18n';

describe('translate', () => {
    it('returns the English key when no catalog applies', () => {
        expect(translate('en', 'Log in')).toBe('Log in');
        expect(translate('fr', 'Log in')).toBe('Log in');
    });

    it('translates into Spanish', () => {
        expect(translate('es', 'Log in')).toBe('Iniciar sesión');
    });

    it('fills Laravel-style placeholders, longest first', () => {
        expect(
            translate('en', ':score of :total (:to)', {
                score: 3,
                total: 4,
                to: 'x',
            }),
        ).toBe('3 of 4 (x)');
    });

    it('maps locales to regional tags', () => {
        expect(regionalLocale('es')).toBe('es-ES');
        expect(regionalLocale('en')).toBe('en-US');
    });
});

/**
 * Every literal passed to t('…') in the frontend must exist in lang/es.json,
 * so a new string can never ship untranslated.
 */
describe('Spanish catalog', () => {
    const sources = import.meta.glob<string>(
        [
            '../**/*.{vue,ts}',
            '!../**/*.test.ts',
            '!../components/ui/**',
            '!../actions/**',
            '!../routes/**',
            '!../wayfinder/**',
        ],
        { query: '?raw', import: 'default', eager: true },
    );

    const keys = new Set<string>();

    for (const source of Object.values(sources)) {
        // t('…') calls, plus title/description/label literals that are
        // translated where they are rendered (nav items, layout props…).
        const patterns = [
            /\bt\(\s*(['"`])((?:(?!\1)[^\\]|\\.)*?)\1/g,
            /\b(?:title|description|label):\s*(['"])((?:(?!\1)[^\\]|\\.)*?)\1/g,
        ];

        for (const pattern of patterns) {
            for (const match of source.matchAll(pattern)) {
                keys.add(match[2].replace(/\\(['"`])/g, '$1'));
            }
        }
    }

    it('finds the translated strings', () => {
        expect(keys.size).toBeGreaterThan(100);
    });

    it('has a translation for every interface string', () => {
        const missing = [...keys].filter((key) => !hasTranslation('es', key));

        expect(missing).toEqual([]);
    });

    it('does not contain empty translations', () => {
        const empty = Object.entries(es as Record<string, string>)
            .filter(([, value]) => value.trim() === '')
            .map(([key]) => key);

        expect(empty).toEqual([]);
    });
});
