<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'title' => rtrim(fake()->unique()->sentence(4), '.'),
            'summary' => fake()->sentence(12),
            'body' => fake()->paragraphs(3, true),
            'status' => LessonStatus::Draft,
            'published_at' => null,
            'position' => fake()->numberBetween(1, 50),
            'duration_seconds' => fake()->numberBetween(180, 1800),
            'is_free' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => LessonStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['is_free' => true]);
    }
}
