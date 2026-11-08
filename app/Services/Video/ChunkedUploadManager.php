<?php

namespace App\Services\Video;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Resumable chunked uploads: the browser splits the file and sends each chunk as a raw
 * request body, so no single request exceeds the proxy/PHP limits. Chunks are written
 * to the private disk and assembled when the client calls complete().
 */
class ChunkedUploadManager
{
    private const CACHE_PREFIX = 'video-upload:';

    /**
     * @return array{id: string, chunk_size: int, total_chunks: int}
     */
    public function start(Lesson $lesson, User $user, string $filename, int $size): array
    {
        $id = (string) Str::uuid();
        $chunkSize = (int) config('media.uploads.chunk_size');

        $upload = [
            'id' => $id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'filename' => $filename,
            'extension' => Str::lower(pathinfo($filename, PATHINFO_EXTENSION)),
            'size' => $size,
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) max(1, ceil($size / $chunkSize)),
        ];

        Cache::put(self::CACHE_PREFIX.$id, $upload, now()->addHours((int) config('media.uploads.ttl_hours')));

        return ['id' => $id, 'chunk_size' => $chunkSize, 'total_chunks' => $upload['total_chunks']];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $id, Lesson $lesson, User $user): array
    {
        /** @var array<string, mixed>|null $upload */
        $upload = Cache::get(self::CACHE_PREFIX.$id);

        if ($upload === null || $upload['lesson_id'] !== $lesson->id || $upload['user_id'] !== $user->id) {
            abort(404, 'Upload not found or expired.');
        }

        return $upload;
    }

    /**
     * @param  array<string, mixed>  $upload
     * @param  resource  $stream
     */
    public function storeChunk(array $upload, int $index, $stream): void
    {
        abort_if($index < 0 || $index >= $upload['total_chunks'], 422, 'Invalid chunk index.');

        $path = $this->chunkDirectory($upload['id'])."/{$index}.part";
        Storage::disk('local')->writeStream($path, $stream);

        $expected = $index === $upload['total_chunks'] - 1
            ? $upload['size'] - ($upload['chunk_size'] * $index)
            : $upload['chunk_size'];

        if (Storage::disk('local')->size($path) !== $expected) {
            Storage::disk('local')->delete($path);
            abort(422, 'Chunk size mismatch, please retry.');
        }
    }

    /**
     * Indexes of chunks that were already received, so an interrupted upload can resume.
     *
     * @param  array<string, mixed>  $upload
     * @return list<int>
     */
    public function receivedChunks(array $upload): array
    {
        $chunks = array_map(
            fn (string $file): int => (int) basename($file, '.part'),
            Storage::disk('local')->files($this->chunkDirectory($upload['id'])),
        );
        sort($chunks);

        return $chunks;
    }

    /**
     * Concatenate all chunks into the final file on the media disk.
     *
     * @param  array<string, mixed>  $upload
     * @return array{directory: string, path: string, size: int}
     */
    public function assemble(array $upload): array
    {
        $local = Storage::disk('local');
        $missing = array_diff(range(0, $upload['total_chunks'] - 1), $this->receivedChunks($upload));

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => __('The upload is incomplete. Missing :count chunk(s).', ['count' => count($missing)])]);
        }

        $media = Storage::disk(config('media.disk'));
        $directory = "videos/{$upload['lesson_id']}/{$upload['id']}";
        $path = "{$directory}/original.{$upload['extension']}";
        $media->makeDirectory($directory);

        $target = fopen($media->path($path), 'wb') ?: throw new RuntimeException('Cannot open target file.');

        try {
            for ($i = 0; $i < $upload['total_chunks']; $i++) {
                $chunk = $local->readStream($this->chunkDirectory($upload['id'])."/{$i}.part")
                    ?? throw new RuntimeException("Missing chunk {$i}.");
                stream_copy_to_stream($chunk, $target);
                fclose($chunk);
            }
        } finally {
            fclose($target);
        }

        $size = $media->size($path);

        if ($size !== $upload['size']) {
            $media->deleteDirectory($directory);
            throw ValidationException::withMessages(['file' => __('The uploaded file is corrupted, please try again.')]);
        }

        $this->forget($upload);

        return ['directory' => $directory, 'path' => $path, 'size' => $size];
    }

    /**
     * @param  array<string, mixed>  $upload
     */
    public function forget(array $upload): void
    {
        Storage::disk('local')->deleteDirectory($this->chunkDirectory($upload['id']));
        Cache::forget(self::CACHE_PREFIX.$upload['id']);
    }

    /**
     * Delete chunk folders of abandoned uploads.
     */
    public function prune(): int
    {
        $local = Storage::disk('local');
        $threshold = now()->subHours((int) config('media.uploads.ttl_hours'))->getTimestamp();
        $deleted = 0;

        foreach ($local->directories('video-chunks') as $directory) {
            if ($local->lastModified($directory) < $threshold) {
                $local->deleteDirectory($directory);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function chunkDirectory(string $id): string
    {
        return "video-chunks/{$id}";
    }
}
