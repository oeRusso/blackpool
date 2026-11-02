<?php

namespace App\Actions\Learning;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

class UpdateLessonProgress
{
    public function for(User $user, Lesson $lesson): LessonProgress
    {
        return LessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
    }

    public function savePosition(User $user, Lesson $lesson, int $seconds): LessonProgress
    {
        $progress = $this->for($user, $lesson);
        $progress->update(['last_position_seconds' => max(0, $seconds)]);

        return $progress;
    }

    public function complete(User $user, Lesson $lesson): LessonProgress
    {
        $progress = $this->for($user, $lesson);
        $progress->completed_at ??= now();
        $progress->save();

        return $progress;
    }

    public function toggleComplete(User $user, Lesson $lesson): LessonProgress
    {
        $progress = $this->for($user, $lesson);
        $progress->update(['completed_at' => $progress->completed_at ? null : now()]);

        return $progress;
    }

    public function toggleFavorite(User $user, Lesson $lesson): LessonProgress
    {
        $progress = $this->for($user, $lesson);
        $progress->update(['favorited_at' => $progress->favorited_at ? null : now()]);

        return $progress;
    }
}
