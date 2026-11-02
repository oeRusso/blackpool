import { describe, expect, it, vi } from 'vitest';
import { useChunkedUpload } from '@/composables/useChunkedUpload';

const endpoints = {
    start: () => '/start',
    chunk: (id: string, index: number) => `/uploads/${id}/chunks/${index}`,
    complete: (id: string) => `/uploads/${id}/complete`,
};

const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), { status });

describe('useChunkedUpload', () => {
    it('splits the file into chunks and reports progress', async () => {
        const fetchMock = vi.fn(async (url: string, _init?: RequestInit) => {
            if (url === '/start')
                return json({ id: 'u1', chunk_size: 4, total_chunks: 3 }, 201);
            if (url.endsWith('/complete'))
                return json({ video: { status: 'processing' } }, 201);
            return json({ received: true });
        });
        vi.stubGlobal('fetch', fetchMock);

        const uploader = useChunkedUpload<{ video: { status: string } }>(
            endpoints,
        );
        const result = await uploader.upload(
            new File(['0123456789'], 'clip.mp4'),
        );

        expect(result).toEqual({ video: { status: 'processing' } });
        const chunkCalls = fetchMock.mock.calls.filter(([url]) =>
            url.includes('/chunks/'),
        );
        expect(chunkCalls.map(([url]) => url)).toEqual([
            '/uploads/u1/chunks/0',
            '/uploads/u1/chunks/1',
            '/uploads/u1/chunks/2',
        ]);
        expect((chunkCalls[2][1] as RequestInit).body).toBeInstanceOf(Blob);
        expect(((chunkCalls[2][1] as RequestInit).body as Blob).size).toBe(2);
        expect(uploader.percentage.value).toBe(100);
        expect(uploader.uploading.value).toBe(false);
    });

    it('retries a failing chunk and then succeeds', async () => {
        vi.useFakeTimers();
        let failures = 1;
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (url === '/start')
                    return json(
                        { id: 'u2', chunk_size: 10, total_chunks: 1 },
                        201,
                    );
                if (url.includes('/chunks/') && failures-- > 0)
                    return json({ message: 'boom' }, 500);
                return json({ ok: true });
            }),
        );

        const uploader = useChunkedUpload(endpoints);
        const promise = uploader.upload(new File(['abc'], 'clip.mp4'));
        await vi.runAllTimersAsync();

        await expect(promise).resolves.toEqual({ ok: true });
        expect(uploader.error.value).toBeNull();
        vi.useRealTimers();
    });

    it('surfaces validation errors without retrying', async () => {
        const fetchMock = vi.fn(async () =>
            json(
                {
                    message: 'Invalid',
                    errors: { filename: ['Allowed formats: mp4.'] },
                },
                422,
            ),
        );
        vi.stubGlobal('fetch', fetchMock);

        const uploader = useChunkedUpload(endpoints);
        const result = await uploader.upload(new File(['x'], 'notes.txt'));

        expect(result).toBeNull();
        expect(uploader.error.value).toBe('Allowed formats: mp4.');
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});
