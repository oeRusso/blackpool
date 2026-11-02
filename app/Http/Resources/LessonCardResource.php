<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $thumbnail = $this->coverUrl() ?? ($this->relationLoaded('video') ? $this->video?->toPlayerArray()['thumbnail_url'] : null);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'level' => $this->level->code,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->pluck('name')),
            'is_free' => $this->is_free,
            'duration_seconds' => $this->duration_seconds,
            'image_url' => $thumbnail,
            'has_video' => $this->relationLoaded('video') && $this->video !== null,
            'completed' => (bool) ($this->completed_at ?? false),
        ];
    }
}
