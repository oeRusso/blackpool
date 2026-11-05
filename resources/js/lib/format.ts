/**
 * Dates are formatted in UTC with an explicit locale so the server-rendered
 * HTML (Node) and the hydrated page (browser) always produce the same text.
 */
export function formatDate(
    value: string | null | undefined,
    locale = 'en',
): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(value));
}

export function formatDuration(seconds: number | null | undefined): string {
    if (!seconds) {
        return '—';
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return minutes > 0
        ? `${minutes} min${rest ? ` ${rest}s` : ''}`
        : `${rest}s`;
}
