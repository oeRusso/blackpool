<?php

namespace App\Services\Video;

use App\Enums\VideoSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Turns a YouTube or Vimeo URL into a provider + video id and validates it with the
 * provider's public oEmbed endpoint (free, no API key required).
 */
class EmbedResolver
{
    private const YOUTUBE_PATTERN = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

    private const VIMEO_PATTERN = '~^(?:https?://)?(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{6,12})~';

    /**
     * @return array{source: VideoSource, id: string, url: string}|null
     */
    public function parse(string $url): ?array
    {
        $url = trim($url);

        if (preg_match(self::YOUTUBE_PATTERN, $url, $matches)) {
            return ['source' => VideoSource::YouTube, 'id' => $matches[1], 'url' => "https://www.youtube.com/watch?v={$matches[1]}"];
        }

        if (preg_match(self::VIMEO_PATTERN, $url, $matches)) {
            return ['source' => VideoSource::Vimeo, 'id' => $matches[1], 'url' => "https://vimeo.com/{$matches[1]}"];
        }

        return null;
    }

    /**
     * @return array{source: VideoSource, id: string, url: string, title: string|null, thumbnail_url: string|null}
     *
     * @throws ValidationException
     */
    public function resolve(string $url): array
    {
        $parsed = $this->parse($url) ?? throw ValidationException::withMessages([
            'url' => __('Paste a valid YouTube or Vimeo link.'),
        ]);

        $endpoint = match ($parsed['source']) {
            VideoSource::YouTube => 'https://www.youtube.com/oembed',
            VideoSource::Vimeo => 'https://vimeo.com/api/oembed.json',
            VideoSource::Upload => throw new \LogicException('Uploads are not embeds.'),
        };

        $response = Http::timeout(8)->retry(2, 200, throw: false)->acceptJson()
            ->get($endpoint, ['url' => $parsed['url'], 'format' => 'json']);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'url' => __('This video is private, deleted or does not allow embedding.'),
            ]);
        }

        return [
            ...$parsed,
            'title' => $response->json('title'),
            'thumbnail_url' => $response->json('thumbnail_url'),
        ];
    }
}
