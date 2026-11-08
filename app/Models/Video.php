<?php

namespace App\Models;

use App\Enums\VideoSource;
use App\Enums\VideoStatus;
use Carbon\CarbonInterface;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $lesson_id
 * @property VideoSource $source
 * @property VideoStatus $status
 * @property string|null $external_id
 * @property string|null $external_url
 * @property string|null $title
 * @property string|null $thumbnail_url
 * @property string|null $disk
 * @property string|null $directory
 * @property string|null $original_name
 * @property string|null $original_path
 * @property string|null $playlist_path
 * @property string|null $thumbnail_path
 * @property list<string>|null $renditions
 * @property int|null $size_bytes
 * @property int|null $duration_seconds
 * @property string|null $error
 * @property CarbonInterface|null $processed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable([
    'lesson_id', 'source', 'status', 'external_id', 'external_url', 'title', 'thumbnail_url',
    'disk', 'directory', 'original_name', 'original_path', 'playlist_path', 'thumbnail_path',
    'renditions', 'size_bytes', 'duration_seconds', 'error', 'processed_at',
])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Remove every stored file when an uploaded video is replaced or deleted.
        static::deleted(function (Video $video): void {
            if ($video->disk && $video->directory) {
                Storage::disk($video->disk)->deleteDirectory($video->directory);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => VideoSource::class,
            'status' => VideoStatus::class,
            'renditions' => 'array',
            'processed_at' => 'datetime',
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isReady(): bool
    {
        return $this->status === VideoStatus::Ready;
    }

    public function embedUrl(): ?string
    {
        return match ($this->source) {
            VideoSource::YouTube => "https://www.youtube-nocookie.com/embed/{$this->external_id}?rel=0&modestbranding=1",
            VideoSource::Vimeo => "https://player.vimeo.com/video/{$this->external_id}?dnt=1",
            VideoSource::Upload => null,
        };
    }

    /**
     * Data for the player and admin UI. Upload URLs point to authorized stream routes.
     *
     * @return array<string, mixed>
     */
    public function toPlayerArray(): array
    {
        $lesson = $this->lesson;

        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'embed_url' => $this->embedUrl(),
            'external_url' => $this->external_url,
            'playlist_url' => $this->source === VideoSource::Upload && $this->isReady()
                ? route('lessons.video.stream', ['lesson' => $lesson->slug, 'path' => 'master.m3u8'])
                : null,
            'thumbnail_url' => $this->source === VideoSource::Upload
                ? ($this->thumbnail_path ? route('lessons.video.stream', ['lesson' => $lesson->slug, 'path' => 'thumbnail.jpg']) : null)
                : $this->thumbnail_url,
            'renditions' => $this->renditions ?? [],
            'duration_seconds' => $this->duration_seconds,
            'size_bytes' => $this->size_bytes,
            'original_name' => $this->original_name,
            'error' => $this->error,
        ];
    }
}
