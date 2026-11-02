<?php

namespace App\Http\Controllers\Learning;

use App\Actions\Learning\GradeQuiz;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonCardResource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson): Response
    {
        Gate::authorize('view', $lesson);

        $user = $request->user();
        $access = Gate::inspect('watch', $lesson);
        $canWatch = $access->allowed();

        $lesson->load(['level', 'categories:id,name,slug', 'video', 'author:id,name']);

        $progress = $user
            ? LessonProgress::firstWhere(['user_id' => $user->id, 'lesson_id' => $lesson->id])
            : null;

        $lastAttempt = $user
            ? QuizAttempt::where(['user_id' => $user->id, 'lesson_id' => $lesson->id])->latest('id')->first()
            : null;

        $siblings = Lesson::published()->where('level_id', $lesson->level_id)
            ->orderBy('position')->orderBy('id')->get(['id', 'slug', 'title', 'position']);
        $index = $siblings->search(fn (Lesson $sibling) => $sibling->id === $lesson->id);

        $related = Lesson::published()
            ->whereKeyNot($lesson->id)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $lesson->categories->pluck('id')))
            ->with(['level:id,code', 'video'])
            ->inRandomOrder()->limit(3)->get()
            ->map(fn (Lesson $related) => (new LessonCardResource($related))->resolve($request));

        return Inertia::render('learn/Lesson', [
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'body' => $lesson->body,
                'level' => ['code' => $lesson->level->code, 'name' => $lesson->level->name],
                'categories' => $lesson->categories->map->only(['name', 'slug']),
                'is_free' => $lesson->is_free,
                'duration_seconds' => $lesson->duration_seconds,
                'cover_url' => $lesson->coverUrl(),
                'published_at' => $lesson->published_at?->toIso8601String(),
                'author' => $lesson->author?->name,
                'has_pdf' => $lesson->pdf_path !== null,
                'is_published' => $lesson->isPublished(),
            ],
            'access' => [
                'canWatch' => $canWatch,
                'reason' => $canWatch ? null : $access->message(),
                'requiresAccount' => $user === null,
            ],
            'video' => $lesson->video && ($canWatch || $lesson->video->source->isEmbed() === false)
                ? $this->videoFor($lesson, $canWatch)
                : null,
            'quiz' => $canWatch ? $this->quiz($lesson, $lastAttempt) : null,
            'progress' => [
                'completed' => (bool) $progress?->completed_at,
                'favorite' => (bool) $progress?->favorited_at,
                'position' => $progress->last_position_seconds ?? 0,
            ],
            'navigation' => [
                'previous' => $index !== false && $index > 0 ? $siblings[$index - 1]->only(['slug', 'title']) : null,
                'next' => $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1]->only(['slug', 'title']) : null,
                'position' => $index !== false ? $index + 1 : null,
                'total' => $siblings->count(),
            ],
            'related' => $related,
        ]);
    }

    public function worksheet(Lesson $lesson): StreamedResponse
    {
        Gate::authorize('watch', $lesson);
        abort_if($lesson->pdf_path === null, 404);

        return Storage::disk('local')->download($lesson->pdf_path, "{$lesson->slug}.pdf");
    }

    public function submitQuiz(Request $request, Lesson $lesson, GradeQuiz $gradeQuiz): RedirectResponse
    {
        Gate::authorize('watch', $lesson);
        abort_unless($lesson->questions()->exists(), 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['integer'],
        ]);

        $attempt = $gradeQuiz->handle($request->user(), $lesson, $validated['answers']);
        $passed = $attempt->percentage() >= GradeQuiz::PASS_PERCENTAGE;

        Inertia::flash('toast', [
            'type' => $passed ? 'success' : 'warning',
            'message' => $passed
                ? __('Great job! You scored :score/:total and completed the lesson.', ['score' => $attempt->score, 'total' => $attempt->total])
                : __('You scored :score/:total. You need :pass% to pass, try again!', ['score' => $attempt->score, 'total' => $attempt->total, 'pass' => GradeQuiz::PASS_PERCENTAGE]),
        ]);

        return back();
    }

    /**
     * Locked premium uploads only expose the thumbnail as a teaser.
     *
     * @return array<string, mixed>
     */
    private function videoFor(Lesson $lesson, bool $canWatch): array
    {
        $video = $lesson->video?->toPlayerArray() ?? [];

        return $canWatch ? $video : [
            'source' => $video['source'],
            'status' => $video['status'],
            'thumbnail_url' => $video['thumbnail_url'],
            'duration_seconds' => $video['duration_seconds'],
        ];
    }

    /**
     * Questions without the answers, plus the result of the last attempt (which reveals them).
     *
     * @return array<string, mixed>|null
     */
    private function quiz(Lesson $lesson, ?QuizAttempt $attempt): ?array
    {
        $questions = $lesson->questions()->with('options')->get();

        if ($questions->isEmpty()) {
            return null;
        }

        return [
            'passPercentage' => GradeQuiz::PASS_PERCENTAGE,
            'questions' => $questions->map(fn (Question $question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'options' => $question->options->map->only(['id', 'text']),
                'explanation' => $attempt ? $question->explanation : null,
                'correct_option_id' => $attempt ? $question->options->firstWhere('is_correct', true)?->id : null,
            ]),
            'lastAttempt' => $attempt ? [
                'score' => $attempt->score,
                'total' => $attempt->total,
                'percentage' => $attempt->percentage(),
                'passed' => $attempt->percentage() >= GradeQuiz::PASS_PERCENTAGE,
                'answers' => (object) $attempt->answers,
                'created_at' => $attempt->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
