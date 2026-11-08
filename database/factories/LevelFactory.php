<?php

namespace Database\Factories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('?#'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(1, 6),
        ];
    }
}
