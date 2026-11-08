import { computed, ref } from 'vue';
import { HttpError, sendJson } from '@/lib/http';

export type ChunkedUploadEndpoints = {
    start: () => string;
    chunk: (uploadId: string, index: number) => string;
    complete: (uploadId: string) => string;
};

type StartResponse = { id: string; chunk_size: number; total_chunks: number };

const MAX_ATTEMPTS = 3;

/**
 * Uploads a large file as a sequence of raw chunks (each below the proxy body limit),
 * retrying failed chunks with exponential backoff. Supports cancellation.
 */
export function useChunkedUpload<TComplete>(endpoints: ChunkedUploadEndpoints) {
    const uploadedBytes = ref(0);
    const totalBytes = ref(0);
    const uploading = ref(false);
    const error = ref<string | null>(null);
    let controller: AbortController | null = null;

    const percentage = computed(() =>
        totalBytes.value === 0
            ? 0
            : Math.round((uploadedBytes.value / totalBytes.value) * 100),
    );

    async function sendChunk(url: string, blob: Blob): Promise<void> {
        for (let attempt = 1; ; attempt++) {
            try {
                await sendJson(url, 'PUT', blob, controller?.signal);

                return;
            } catch (exception) {
                const retryable =
                    !(exception instanceof HttpError) ||
                    exception.status >= 500 ||
                    exception.status === 422;

                if (
                    attempt >= MAX_ATTEMPTS ||
                    !retryable ||
                    controller?.signal.aborted
                ) {
                    throw exception;
                }

                await new Promise((resolve) =>
                    setTimeout(resolve, 500 * 2 ** attempt),
                );
            }
        }
    }

    async function upload(file: File): Promise<TComplete | null> {
        controller = new AbortController();
        uploading.value = true;
        error.value = null;
        uploadedBytes.value = 0;
        totalBytes.value = file.size;

        try {
            const session = await sendJson<StartResponse>(
                endpoints.start(),
                'POST',
                { filename: file.name, size: file.size },
                controller.signal,
            );

            for (let index = 0; index < session.total_chunks; index++) {
                const start = index * session.chunk_size;
                const blob = file.slice(start, start + session.chunk_size);
                await sendChunk(endpoints.chunk(session.id, index), blob);
                uploadedBytes.value = Math.min(file.size, start + blob.size);
            }

            return await sendJson<TComplete>(
                endpoints.complete(session.id),
                'POST',
                {},
                controller.signal,
            );
        } catch (exception) {
            if (controller?.signal.aborted) {
                error.value = 'Upload cancelled.';
            } else {
                error.value =
                    exception instanceof HttpError
                        ? exception.firstError()
                        : 'Network error, please try again.';
            }

            return null;
        } finally {
            uploading.value = false;
            controller = null;
        }
    }

    function cancel(): void {
        controller?.abort();
    }

    return {
        upload,
        cancel,
        uploading,
        percentage,
        uploadedBytes,
        totalBytes,
        error,
    };
}
