<?php

return [

    /*
    | Disk where uploaded videos and their HLS renditions are stored. It must be
    | a local disk because ffmpeg reads and writes real paths.
    */
    'disk' => env('MEDIA_DISK', 'media'),

    /*
    | Chunked uploads. Each chunk is a separate request, so the chunk size must stay
    | well below Cloudflare's 100 MB request limit (free plan) and PHP's post_max_size.
    */
    'uploads' => [
        'chunk_size' => (int) env('MEDIA_CHUNK_SIZE', 8 * 1024 * 1024),
        'max_size' => (int) env('MEDIA_MAX_UPLOAD_SIZE', 2 * 1024 * 1024 * 1024),
        'extensions' => ['mp4', 'mov', 'm4v', 'webm', 'mkv'],
        'ttl_hours' => 24,
    ],

    'ffmpeg' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'timeout' => (int) env('FFMPEG_TIMEOUT', 3600),
        'threads' => (int) env('FFMPEG_THREADS', 0),
    ],

    /*
    | HLS renditions, from lowest to highest. A rendition is only generated when the
    | source is at least as tall as it (the lowest one is always generated).
    */
    'renditions' => [
        ['name' => '360p', 'height' => 360, 'video_bitrate' => '800k', 'audio_bitrate' => '96k'],
        ['name' => '720p', 'height' => 720, 'video_bitrate' => '2800k', 'audio_bitrate' => '128k'],
    ],

    'hls_segment_seconds' => 6,

    /*
    | When serving through Nginx, set this to the internal location that maps to the
    | media disk root (e.g. "/protected-media") so PHP only authorizes the request and
    | Nginx streams the bytes with X-Accel-Redirect.
    */
    'accel_redirect_prefix' => env('MEDIA_ACCEL_REDIRECT_PREFIX'),

];
