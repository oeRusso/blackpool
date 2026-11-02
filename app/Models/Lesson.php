<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $level_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string|null $body
 * @property LessonStatus $status
 * @property Carbon|null $published_at
 * @property int $position
 * @property int|null $duration_seconds
 * @property bool $is_free
 * @property string|null $cover_path
 * @property string|null $pdf_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Level $level
 * @property-read User|null $author
 * @property-read Video|null $video
 */
#[Fillable([
    'level_id', 'author_id', 'title', 'slug', 'summary', 'body', 'status',
    'published_at', 'position', 'duration_seconds', 'is_free', 'cover_path', 'pdf_path',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson): void {
            if (blank($lesson->slug)) {
                $lesson->slug = static::uniqueSlug($lesson->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LessonStatus::class,
            'published_at' => 'datetime',
            'is_free' => 'boolean',
            'position' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasOne<Video, $this>
     */
    public function video(): HasOne
    {
        return $this->hasOne(Video::class);
    }

    /**
     * Lessons visible to the public: published and with a publication date in the past.
     *
     * @param  Builder<Lesson>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', LessonStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === LessonStatus::Published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
