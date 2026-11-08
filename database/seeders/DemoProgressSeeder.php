<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoProgressSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'student@blackpool.test')->first();

        if ($student === null) {
            return;
        }

        $lessons = Lesson::whereIn('slug', ['hello-greetings-and-introductions', 'the-verb-to-be', 'present-simple-vs-present-continuous'])
            ->get()->keyBy('slug');

        $rows = [
            'hello-greetings-and-introductions' => ['completed_at' => now()->subDays(2), 'favorited_at' => now()->subDays(2)],
            'the-verb-to-be' => ['last_position_seconds' => 95],
            'present-simple-vs-present-continuous' => ['favorited_at' => now()->subDay()],
        ];

        foreach ($rows as $slug => $attributes) {
            if ($lesson = $lessons->get($slug)) {
                LessonProgress::updateOrCreate(['user_id' => $student->id, 'lesson_id' => $lesson->id], $attributes);
            }
        }
    }
}
