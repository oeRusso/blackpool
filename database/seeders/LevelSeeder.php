<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * Common European Framework of Reference (CEFR) levels.
     */
    public const LEVELS = [
        ['code' => 'A1', 'name' => 'Beginner', 'description' => 'Understand and use familiar everyday expressions and very basic phrases.'],
        ['code' => 'A2', 'name' => 'Elementary', 'description' => 'Communicate in simple and routine tasks on familiar topics.'],
        ['code' => 'B1', 'name' => 'Intermediate', 'description' => 'Deal with most situations likely to arise while travelling.'],
        ['code' => 'B2', 'name' => 'Upper intermediate', 'description' => 'Interact with a degree of fluency and spontaneity with native speakers.'],
        ['code' => 'C1', 'name' => 'Advanced', 'description' => 'Express ideas fluently and use language flexibly for social, academic and professional purposes.'],
        ['code' => 'C2', 'name' => 'Proficient', 'description' => 'Understand with ease virtually everything heard or read.'],
    ];

    public function run(): void
    {
        foreach (self::LEVELS as $position => $level) {
            Level::updateOrCreate(['code' => $level['code']], [...$level, 'position' => $position + 1]);
        }
    }
}
