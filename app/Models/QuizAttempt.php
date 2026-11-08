<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $score
 * @property int $total
 * @property array<int, int> $answers
 * @property CarbonInterface|null $created_at
 * @property-read Lesson $lesson
 */
#[Fillable(['user_id', 'lesson_id', 'score', 'total', 'answers'])]
class QuizAttempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answers' => 'array'];
    }

    public function percentage(): int
    {
        return $this->total > 0 ? (int) round($this->score / $this->total * 100) : 0;
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
