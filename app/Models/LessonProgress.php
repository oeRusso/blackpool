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
 * @property int $last_position_seconds
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $favorited_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Lesson $lesson
 */
#[Fillable(['user_id', 'lesson_id', 'last_position_seconds', 'completed_at', 'favorited_at'])]
class LessonProgress extends Model
{
    protected $table = 'lesson_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'favorited_at' => 'datetime',
            'last_position_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
