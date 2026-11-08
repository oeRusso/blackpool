<?php

namespace Database\Factories;

use App\Enums\VideoSource;
use App\Enums\VideoStatus;
use App\Models\Lesson;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'source' => VideoSource::YouTube,
            'status' => VideoStatus::Ready,
            'external_id' => 'dQw4w9WgXcQ',
            'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => fake()->sentence(3),
        ];
    }
}
