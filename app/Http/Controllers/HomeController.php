<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonCardResource;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $levels = Cache::remember('home.levels', now()->addMinutes(10), fn () => Level::orderBy('position')
            ->withCount(['lessons' => fn ($q) => $q->published()])
            ->get(['id', 'code', 'name', 'description'])
            ->map->only(['code', 'name', 'description', 'lessons_count'])
            ->all());

        $featured = Lesson::published()->where('is_free', true)
            ->with(['level:id,code', 'categories:id,name', 'video'])
            ->join('levels', 'levels.id', '=', 'lessons.level_id')
            ->orderBy('levels.position')->select('lessons.*')
            ->limit(6)->get()
            ->map(fn (Lesson $lesson) => (new LessonCardResource($lesson))->resolve($request));

        return Inertia::render('Welcome', [
            'levels' => $levels,
            'featured' => $featured,
            'stats' => [
                'lessons' => Lesson::published()->count(),
                'free' => Lesson::published()->where('is_free', true)->count(),
                'categories' => Category::count(),
            ],
        ]);
    }
}
