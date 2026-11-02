const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

export function formatDate(value: string | null | undefined): string {
    return value ? dateFormatter.format(new Date(value)) : '—';
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
