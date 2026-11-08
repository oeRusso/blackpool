<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Thin wrapper around ffprobe/ffmpeg to produce adaptive HLS renditions and a thumbnail.
 */
class HlsTranscoder
{
    /**
     * @return array{duration: float, width: int, height: int, has_audio: bool}
     */
    public function probe(string $input): array
    {
        $result = Process::timeout(120)->run([
            config('media.ffmpeg.ffprobe'), '-v', 'error', '-print_format', 'json',
            '-show_format', '-show_streams', $input,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('ffprobe could not read the file: '.trim($result->errorOutput()));
        }

        /** @var array{format?: array{duration?: string}, streams?: list<array<string, mixed>>} $data */
        $data = json_decode($result->output(), true) ?? [];
        $streams = collect($data['streams'] ?? []);
        $video = $streams->firstWhere('codec_type', 'video');

        if ($video === null) {
            throw new RuntimeException('The file does not contain a video stream.');
        }

        return [
            'duration' => (float) ($data['format']['duration'] ?? $video['duration'] ?? 0),
            'width' => (int) $video['width'],
            'height' => (int) $video['height'],
            'has_audio' => $streams->contains('codec_type', 'audio'),
        ];
    }

    public function thumbnail(string $input, string $output, float $duration): void
    {
        $at = max(0.0, min($duration * 0.1, 10.0));

        $this->run([
            '-ss', number_format($at, 2, '.', ''), '-i', $input,
            '-frames:v', '1', '-vf', 'scale=1280:-2', '-q:v', '3', $output,
        ]);
    }

    /**
     * Create one HLS variant per rendition plus a master playlist.
     *
     * @param  array{width: int, height: int, has_audio: bool}  $probe
     * @return list<string> Names of the generated renditions.
     */
    public function transcode(string $input, string $outputDirectory, array $probe): array
    {
        /** @var list<array{name: string, height: int, video_bitrate: string, audio_bitrate: string}> $all */
        $all = config('media.renditions');
        $renditions = array_values(array_filter($all, fn (array $r) => $r['height'] <= $probe['height'])) ?: [$all[0]];

        $count = count($renditions);
        $split = '[0:v]split='.$count.implode('', array_map(fn ($i) => "[v{$i}]", range(0, $count - 1)));
        $filters = [$split];
        $args = ['-i', $input];
        $streamMap = [];

        foreach ($renditions as $i => $rendition) {
            $filters[] = "[v{$i}]scale=-2:{$rendition['height']},format=yuv420p[v{$i}out]";
            array_push($args,
                '-map', "[v{$i}out]",
                "-c:v:{$i}", 'libx264', '-preset', 'veryfast', '-profile:v', 'main',
                "-b:v:{$i}", $rendition['video_bitrate'],
                "-maxrate:v:{$i}", $rendition['video_bitrate'],
                "-bufsize:v:{$i}", ((int) $rendition['video_bitrate'] * 2).'k',
            );

            if ($probe['has_audio']) {
                array_push($args, '-map', 'a:0', "-c:a:{$i}", 'aac', "-b:a:{$i}", $rendition['audio_bitrate'], '-ac', '2');
                $streamMap[] = "v:{$i},a:{$i},name:{$rendition['name']}";
            } else {
                $streamMap[] = "v:{$i},name:{$rendition['name']}";
            }
        }

        $segment = (int) config('media.hls_segment_seconds');

        array_push($args,
            '-filter_complex', implode(';', $filters),
            '-g', (string) ($segment * 30), '-keyint_min', (string) ($segment * 30), '-sc_threshold', '0',
            '-threads', (string) config('media.ffmpeg.threads'),
            '-f', 'hls', '-hls_time', (string) $segment, '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_filename', "{$outputDirectory}/%v/segment_%03d.ts",
            '-master_pl_name', 'master.m3u8',
            '-var_stream_map', implode(' ', $streamMap),
            "{$outputDirectory}/%v/index.m3u8",
        );

        $this->run($args);

        return array_column($renditions, 'name');
    }

    /**
     * @param  list<string>  $args
     */
    private function run(array $args): void
    {
        $result = Process::timeout((int) config('media.ffmpeg.timeout'))
            ->run([config('media.ffmpeg.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y', ...$args]);

        if ($result->failed()) {
            throw new RuntimeException('ffmpeg failed: '.trim($result->errorOutput()));
        }
    }
}
