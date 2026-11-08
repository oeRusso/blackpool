<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonCardResource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My learning": progress per CEFR level, lessons in progress, favourites and quiz results.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $progress = LessonProgress::where('user_id', $user->id)
            ->whereHas('lesson', fn ($q) => $q->published())
            ->with(['lesson' => fn ($q) => $q->with(['level:id,code', 'video'])])
            ->latest('updated_at')
            ->get();

        $completedIds = $progress->whereNotNull('completed_at')->pluck('lesson_id');

        $levels = Level::orderBy('position')
            ->withCount(['lessons as total' => fn ($q) => $q->published()])
            ->withCount(['lessons as completed' => fn ($q) => $q->published()->whereIn('lessons.id', $completedIds)])
            ->get(['id', 'code', 'name'])
            ->map(fn (Level $level) => $level->only(['code', 'name', 'total', 'completed']));

        $card = fn (LessonProgress $p) => (new LessonCardResource($p->lesson))->resolve($request)
            + ['last_position_seconds' => $p->last_position_seconds, 'completed' => $p->completed_at !== null];

        $next = Lesson::published()
            ->whereNotIn('lessons.id', $completedIds)
            ->when(! $user->can('view premium lessons'), fn ($q) => $q->where('is_free', true))
            ->join('levels', 'levels.id', '=', 'lessons.level_id')
            ->orderBy('levels.position')->orderBy('lessons.position')
            ->select('lessons.*')
            ->with(['level:id,code', 'video'])
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'completed' => $completedIds->count(),
                'total' => Lesson::published()->count(),
                'inProgress' => $progress->whereNull('completed_at')->count(),
                'quizzesPassed' => QuizAttempt::where('user_id', $user->id)
                    ->whereRaw('score * 100 >= total * 70')
                    ->distinct('lesson_id')->count('lesson_id'),
                'completedThisWeek' => $progress->where('completed_at', '>=', now()->startOfWeek())->count(),
            ],
            'levels' => $levels,
            'continueLearning' => $progress->whereNull('completed_at')->take(4)->map($card)->values(),
            'favorites' => $progress->whereNotNull('favorited_at')->take(6)->map($card)->values(),
            'recentAttempts' => QuizAttempt::where('user_id', $user->id)->with('lesson:id,title,slug')->latest('id')->limit(5)->get()
                ->map(fn (QuizAttempt $attempt) => [
                    'id' => $attempt->id,
                    'lesson' => $attempt->lesson->only(['title', 'slug']),
                    'score' => $attempt->score,
                    'total' => $attempt->total,
                    'percentage' => $attempt->percentage(),
                    'created_at' => $attempt->created_at?->toIso8601String(),
                ]),
            'nextLesson' => $next ? (new LessonCardResource($next))->resolve($request) : null,
            'isPremium' => $user->can('view premium lessons'),
        ]);
    }
}
