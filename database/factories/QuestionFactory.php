<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'prompt' => rtrim(fake()->sentence(6), '.').'?',
            'explanation' => fake()->sentence(),
            'position' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Attach options; the first one is correct.
     *
     * @param  list<string>  $options
     */
    public function withOptions(array $options = ['Correct', 'Wrong', 'Also wrong']): static
    {
        return $this->afterCreating(function (Question $question) use ($options): void {
            foreach ($options as $position => $text) {
                $question->options()->create(['text' => $text, 'is_correct' => $position === 0, 'position' => $position]);
            }
        });
    }
}
