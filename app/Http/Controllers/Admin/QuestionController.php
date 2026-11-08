<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class QuestionController extends Controller
{
    public function store(Request $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:255'],
            'explanation' => ['nullable', 'string', 'max:500'],
            'options' => ['required', 'array', 'min:2', 'max:5'],
            'options.*' => ['required', 'string', 'max:150', 'distinct'],
            'correct' => ['required', 'integer', 'min:0', 'lt:'.count((array) $request->input('options'))],
        ], [], ['options.*' => __('option')]);

        DB::transaction(function () use ($lesson, $validated): void {
            $question = $lesson->questions()->create([
                'prompt' => $validated['prompt'],
                'explanation' => $validated['explanation'] ?? null,
                'position' => (int) $lesson->questions()->max('position') + 1,
            ]);

            foreach (array_values($validated['options']) as $position => $text) {
                $question->options()->create([
                    'text' => $text,
                    'is_correct' => $position === (int) $validated['correct'],
                    'position' => $position,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question added.')]);

        return back();
    }

    public function destroy(Lesson $lesson, Question $question): RedirectResponse
    {
        Gate::authorize('update', $lesson);
        abort_unless($question->lesson_id === $lesson->id, 404);

        $question->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question deleted.')]);

        return back();
    }
}
