<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonCardResource;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->limit(80, '')->toString(),
            'level' => $request->string('level')->upper()->toString() ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'access' => in_array($request->query('access'), ['free', 'premium'], true) ? $request->query('access') : null,
        ];

        $user = $request->user();

        $lessons = Lesson::query()
            ->published()
            ->with(['level:id,code', 'categories:id,name,slug', 'video'])
            ->select('lessons.*')
            ->when($user, fn (Builder $q) => $q->addSelect([
                'completed_at' => LessonProgress::select('completed_at')
                    ->whereColumn('lesson_id', 'lessons.id')
                    ->where('user_id', $user?->id)
                    ->limit(1),
            ]))
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$filters['q']}%")
                ->orWhere('summary', 'like', "%{$filters['q']}%")))
            ->when($filters['level'], fn (Builder $q, string $code) => $q->whereRelation('level', 'code', $code))
            ->when($filters['category'], fn (Builder $q, string $slug) => $q->whereRelation('categories', 'slug', $slug))
            ->when($filters['access'] === 'free', fn (Builder $q) => $q->where('is_free', true))
            ->when($filters['access'] === 'premium', fn (Builder $q) => $q->where('is_free', false))
            ->join('levels', 'levels.id', '=', 'lessons.level_id')
            ->orderBy('levels.position')
            ->orderBy('lessons.position')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Lesson $lesson) => (new LessonCardResource($lesson))->resolve($request));

        return Inertia::render('learn/Catalog', [
            'lessons' => $lessons,
            'filters' => $filters,
            'levels' => Level::orderBy('position')->get(['id', 'code', 'name', 'description']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
