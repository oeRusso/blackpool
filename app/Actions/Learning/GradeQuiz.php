<?php

namespace App\Actions\Learning;

use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Scores a quiz submission, stores the attempt and completes the lesson when passed.
 */
class GradeQuiz
{
    public const PASS_PERCENTAGE = 70;

    public function __construct(private UpdateLessonProgress $progress) {}

    /**
     * @param  array<int|string, int|string>  $answers  question id => option id
     */
    public function handle(User $user, Lesson $lesson, array $answers): QuizAttempt
    {
        $questions = $lesson->questions()->with('options')->get();
        $normalized = [];
        $score = 0;

        foreach ($questions as $question) {
            $chosen = isset($answers[$question->id]) ? (int) $answers[$question->id] : null;
            $normalized[$question->id] = $chosen;

            if ($question->options->firstWhere('id', $chosen)?->is_correct) {
                $score++;
            }
        }

        $attempt = $user->quizAttempts()->create([
            'lesson_id' => $lesson->id,
            'score' => $score,
            'total' => $questions->count(),
            'answers' => $normalized,
        ]);

        if ($attempt->percentage() >= self::PASS_PERCENTAGE) {
            $this->progress->complete($user, $lesson);
        }

        return $attempt;
    }
}
