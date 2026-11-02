<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\SaveLesson;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lesson::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'level' => $request->integer('level') ?: null,
            'status' => LessonStatus::tryFrom($request->string('status')->toString())?->value,
        ];

        $lessons = Lesson::query()
            ->with(['level:id,code', 'categories:id,name'])
            ->when($filters['search'] !== '', fn (Builder $q) => $q->where('title', 'like', "%{$filters['search']}%"))
            ->when($filters['level'], fn (Builder $q, int $level) => $q->where('level_id', $level))
            ->when($filters['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'level' => $lesson->level->code,
                'categories' => $lesson->categories->pluck('name'),
                'status' => $lesson->status->value,
                'is_free' => $lesson->is_free,
                'published_at' => $lesson->published_at?->toIso8601String(),
                'updated_at' => $lesson->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/lessons/Index', [
            'lessons' => $lessons,
            'filters' => $filters,
            'levels' => Level::orderBy('position')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Lesson::class);

        return Inertia::render('admin/lessons/Form', $this->formOptions());
    }

    public function store(LessonRequest $request, SaveLesson $saveLesson): RedirectResponse
    {
        $lesson = $saveLesson->handle($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson created.')]);

        return to_route('admin.lessons.edit', $lesson);
    }

    public function edit(Lesson $lesson): Response
    {
        Gate::authorize('update', $lesson);

        $lesson->load(['categories:id', 'video']);

        return Inertia::render('admin/lessons/Form', [
            ...$this->formOptions(),
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'summary' => $lesson->summary,
                'body' => $lesson->body,
                'level_id' => $lesson->level_id,
                'category_ids' => $lesson->categories->pluck('id'),
                'is_free' => $lesson->is_free,
                'status' => $lesson->status->value,
                'published_at' => $lesson->published_at?->format('Y-m-d\TH:i'),
                'position' => $lesson->position,
                'cover_url' => $lesson->coverUrl(),
                'has_pdf' => $lesson->pdf_path !== null,
            ],
            'video' => $lesson->video?->toPlayerArray(),
            'uploadLimits' => [
                'max_size' => (int) config('media.uploads.max_size'),
                'extensions' => config('media.uploads.extensions'),
            ],
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson, SaveLesson $saveLesson): RedirectResponse
    {
        $saveLesson->handle($request->validated(), $request->user(), $lesson);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson saved.')]);

        return to_route('admin.lessons.edit', $lesson);
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $lesson->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson moved to trash.')]);

        return to_route('admin.lessons.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'levels' => Level::orderBy('position')->get(['id', 'code', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'statuses' => collect(LessonStatus::cases())->map(fn (LessonStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
        ];
    }
}
