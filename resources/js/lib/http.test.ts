import { afterEach, describe, expect, it, vi } from 'vitest';
import { HttpError, sendJson, xsrfToken } from '@/lib/http';

afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

describe('xsrfToken', () => {
    it('reads and decodes the XSRF cookie', () => {
        document.cookie = 'XSRF-TOKEN=abc%3D%3D';
        expect(xsrfToken()).toBe('abc==');
    });
});

describe('sendJson', () => {
    it('sends JSON with CSRF headers and returns the body', async () => {
        document.cookie = 'XSRF-TOKEN=token';
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                new Response(JSON.stringify({ ok: true }), { status: 200 }),
            );
        vi.stubGlobal('fetch', fetchMock);

        await expect(sendJson('/x', 'POST', { a: 1 })).resolves.toEqual({
            ok: true,
        });

        const [, init] = fetchMock.mock.calls[0];
        expect(init.headers['X-XSRF-TOKEN']).toBe('token');
        expect(init.headers['Content-Type']).toBe('application/json');
        expect(init.body).toBe('{"a":1}');
    });

    it('sends blobs as raw octet streams', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(new Response('{}', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        await sendJson('/chunk', 'PUT', new Blob(['data']));

        expect(fetchMock.mock.calls[0][1].headers['Content-Type']).toBe(
            'application/octet-stream',
        );
    });

    it('throws an HttpError exposing the first validation message', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        message: 'Invalid',
                        errors: { filename: ['Bad format'] },
                    }),
                    { status: 422 },
                ),
            ),
        );

        const error = await sendJson('/x', 'POST', {}).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(HttpError);
        expect((error as HttpError).status).toBe(422);
        expect((error as HttpError).firstError()).toBe('Bad format');
    });
});
