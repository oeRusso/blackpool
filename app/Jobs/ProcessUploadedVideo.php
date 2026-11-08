<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Services\Video\HlsTranscoder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Probes an uploaded video, extracts a thumbnail and transcodes it to adaptive HLS.
 * The original file is deleted afterwards to save disk space.
 */
class ProcessUploadedVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public int $backoff = 30;

    public function __construct(public Video $video)
    {
        $this->onQueue('media');
    }

    public function uniqueId(): string
    {
        return (string) $this->video->id;
    }

    public function handle(HlsTranscoder $transcoder): void
    {
        $video = $this->video;
        $disk = Storage::disk($video->disk ?? config('media.disk'));
        $input = $disk->path((string) $video->original_path);
        $directory = (string) $video->directory;

        $probe = $transcoder->probe($input);

        $transcoder->thumbnail($input, $disk->path("{$directory}/thumbnail.jpg"), $probe['duration']);

        $disk->makeDirectory("{$directory}/hls");
        $renditions = $transcoder->transcode($input, $disk->path("{$directory}/hls"), $probe);

        $disk->delete((string) $video->original_path);

        $video->update([
            'status' => VideoStatus::Ready,
            'playlist_path' => "{$directory}/hls/master.m3u8",
            'thumbnail_path' => "{$directory}/thumbnail.jpg",
            'renditions' => $renditions,
            'duration_seconds' => (int) round($probe['duration']),
            'original_path' => null,
            'error' => null,
            'processed_at' => now(),
        ]);

        $video->lesson->update(['duration_seconds' => (int) round($probe['duration'])]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->video->update([
            'status' => VideoStatus::Failed,
            'error' => str($exception?->getMessage() ?? 'Unknown error')->limit(1000)->toString(),
        ]);
    }
}
