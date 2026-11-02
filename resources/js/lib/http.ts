/** Reads Laravel's XSRF-TOKEN cookie so fetch() requests pass CSRF verification. */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(
        public status: number,
        public body: Record<string, unknown>,
    ) {
        super(
            (body.message as string | undefined) ??
                `Request failed with status ${status}`,
        );
    }

    /** First validation message, if the server returned a 422. */
    firstError(): string {
        const errors = this.body.errors as Record<string, string[]> | undefined;

        return errors
            ? (Object.values(errors)[0]?.[0] ?? this.message)
            : this.message;
    }
}

export async function sendJson<T>(
    url: string,
    method: string,
    body?: BodyInit | Record<string, unknown>,
    signal?: AbortSignal,
): Promise<T> {
    const isRaw = body instanceof Blob || body instanceof ArrayBuffer;
    const response = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            'Content-Type': isRaw
                ? 'application/octet-stream'
                : 'application/json',
        },
        body:
            body === undefined
                ? undefined
                : isRaw
                  ? (body as BodyInit)
                  : JSON.stringify(body),
    });

    const data = (await response.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (!response.ok) {
        throw new HttpError(response.status, data);
    }

    return data as T;
}
