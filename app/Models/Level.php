<?php

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * CEFR level (A1–C2). Levels are reference data seeded by LevelSeeder.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'description', 'position'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
