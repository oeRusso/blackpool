<?php

namespace App\Http\Controllers\Learning;

use App\Actions\Learning\UpdateLessonProgress;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function __construct(private UpdateLessonProgress $progress) {}

    /**
     * Called periodically by the player (JSON, no page reload).
     */
    public function position(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('watch', $lesson);

        $validated = $request->validate(['seconds' => ['required', 'integer', 'min:0', 'max:86400']]);
        $this->progress->savePosition($request->user(), $lesson, $validated['seconds']);

        return response()->json(['saved' => true]);
    }

    public function complete(Request $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('watch', $lesson);

        $progress = $this->progress->toggleComplete($request->user(), $lesson);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $progress->completed_at ? __('Lesson completed. Keep going!') : __('Lesson marked as not completed.'),
        ]);

        return back();
    }

    public function favorite(Request $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('view', $lesson);

        $progress = $this->progress->toggleFavorite($request->user(), $lesson);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $progress->favorited_at ? __('Saved to your favourites.') : __('Removed from your favourites.'),
        ]);

        return back();
    }
}
