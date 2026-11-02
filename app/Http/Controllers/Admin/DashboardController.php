<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LessonStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('access-admin');

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'lessons' => Lesson::count(),
                'published' => Lesson::where('status', LessonStatus::Published)->count(),
                'drafts' => Lesson::where('status', LessonStatus::Draft)->count(),
                'categories' => Category::count(),
                'students' => User::role([RoleName::Student->value, RoleName::Premium->value])->count(),
                'premium' => User::role(RoleName::Premium->value)->count(),
            ],
            'lessonsByLevel' => Level::withCount('lessons')->orderBy('position')->get(['id', 'code', 'name'])
                ->map(fn (Level $level) => ['code' => $level->code, 'name' => $level->name, 'count' => $level->lessons_count]),
            'recentLessons' => Lesson::with('level:id,code')->latest('updated_at')->limit(5)->get()
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'level' => $lesson->level->code,
                    'status' => $lesson->status->value,
                    'updated_at' => $lesson->updated_at?->toIso8601String(),
                ]),
        ]);
    }
}
