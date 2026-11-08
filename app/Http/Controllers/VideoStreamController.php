<?php

namespace App\Http\Controllers;

use App\Enums\VideoSource;
use App\Models\Lesson;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves HLS playlists, segments and the thumbnail of an uploaded lesson video.
 * Every request is authorized with LessonPolicy::watch, so premium videos stay private.
 * In production Nginx streams the bytes through X-Accel-Redirect.
 */
class VideoStreamController extends Controller
{
    private const CONTENT_TYPES = [
        'm3u8' => 'application/vnd.apple.mpegurl',
        'ts' => 'video/mp2t',
        'jpg' => 'image/jpeg',
    ];

    public function __invoke(Lesson $lesson, string $path): Response
    {
        $video = $lesson->video;
        abort_if($video === null || $video->source !== VideoSource::Upload, 404);

        // Thumbnails are public preview images; the stream itself is protected.
        if ($path !== 'thumbnail.jpg') {
            Gate::authorize('watch', $lesson);
        }

        $relative = $path === 'thumbnail.jpg'
            ? (string) $video->thumbnail_path
            : "{$video->directory}/hls/{$path}";

        $disk = Storage::disk((string) $video->disk);
        abort_unless($relative !== '' && $disk->exists($relative), 404);

        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $headers = [
            'Content-Type' => self::CONTENT_TYPES[$extension] ?? 'application/octet-stream',
            'Cache-Control' => $extension === 'm3u8' ? 'private, no-cache' : 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($prefix = config('media.accel_redirect_prefix')) {
            return response('', 200, [...$headers, 'X-Accel-Redirect' => rtrim($prefix, '/').'/'.$relative]);
        }

        return response()->file($disk->path($relative), $headers);
    }
}
