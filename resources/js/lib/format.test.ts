import { describe, expect, it } from 'vitest';
import { formatDate, formatDuration } from '@/lib/format';

describe('formatDuration', () => {
    it.each([
        [null, '—'],
        [0, '—'],
        [45, '45s'],
        [300, '5 min'],
        [425, '7 min 5s'],
    ])('formats %s seconds as %s', (seconds, expected) => {
        expect(formatDuration(seconds)).toBe(expected);
    });
});

describe('formatDate', () => {
    it('returns a dash for empty values', () => {
        expect(formatDate(null)).toBe('—');
    });

    it('formats ISO dates in the given locale and in UTC', () => {
        expect(formatDate('2026-11-02T23:30:00Z')).toBe('Nov 2, 2026');
        expect(formatDate('2026-11-02T23:30:00Z', 'es')).toBe('2 nov 2026');
    });
});
