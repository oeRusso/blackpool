<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Level;
use App\Models\Question;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

it('lists only published lessons in the public catalog', function () {
    $published = Lesson::factory()->published()->create(['title' => 'Visible lesson']);
    Lesson::factory()->create(['title' => 'Draft lesson']);
    Lesson::factory()->create(['title' => 'Scheduled lesson', 'status' => 'published', 'published_at' => now()->addWeek()]);

    $this->get(route('lessons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('learn/Catalog')
            ->has('lessons.data', 1)
            ->where('lessons.data.0.slug', $published->slug));
});

it('filters the catalog by level, category, access and search', function () {
    $a1 = Level::factory()->create(['code' => 'A1']);
    $b2 = Level::factory()->create(['code' => 'B2']);
    $grammar = Category::factory()->create(['name' => 'Grammar']);
    $free = Lesson::factory()->published()->free()->for($a1)->create(['title' => 'Verb to be']);
    $free->categories()->attach($grammar);
    Lesson::factory()->published()->for($b2)->create(['title' => 'Passive voice']);

    $slugs = fn (array $query) => collect($this->get(route('lessons.index', $query))->viewData('page')['props']['lessons']['data'])->pluck('slug')->all();

    expect($slugs(['level' => 'a1']))->toBe([$free->slug])
        ->and($slugs(['category' => 'grammar']))->toBe([$free->slug])
        ->and($slugs(['access' => 'premium']))->toBe(['passive-voice'])
        ->and($slugs(['q' => 'passive']))->toBe(['passive-voice']);
});

it('hides drafts from students but lets staff preview them', function () {
    $draft = Lesson::factory()->create();

    $this->get(route('lessons.show', $draft->slug))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Student))->get(route('lessons.show', $draft->slug))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Editor))->get(route('lessons.show', $draft->slug))->assertOk();
});

it('applies the access rules for guests, students and premium members', function (?RoleName $role, bool $free, bool $canWatch) {
    $lesson = Lesson::factory()->published()->create(['is_free' => $free]);
    Video::factory()->for($lesson)->create();
    Question::factory()->for($lesson)->withOptions()->create();

    if ($role) {
        $this->actingAs(userWithRole($role));
    }

    $this->get(route('lessons.show', $lesson->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('access.canWatch', $canWatch)
            ->where('video', $canWatch ? fn ($video) => $video['embed_url'] !== null : null)
            ->where('quiz', $canWatch ? fn ($quiz) => count($quiz['questions']) === 1 : null));
})->with([
    'guest, free lesson' => [null, true, false],
    'student, free lesson' => [RoleName::Student, true, true],
    'student, premium lesson' => [RoleName::Student, false, false],
    'premium, premium lesson' => [RoleName::Premium, false, true],
    'editor, premium lesson' => [RoleName::Editor, false, true],
]);

it('never sends the correct answers before the student answers', function () {
    $lesson = Lesson::factory()->published()->free()->create();
    Question::factory()->for($lesson)->withOptions()->create();

    $this->actingAs(userWithRole(RoleName::Student))
        ->get(route('lessons.show', $lesson->slug))
        ->assertInertia(fn ($page) => $page
            ->where('quiz.questions.0.correct_option_id', null)
            ->missing('quiz.questions.0.options.0.is_correct'));
});

it('grades a quiz and completes the lesson when passed', function () {
    $student = userWithRole(RoleName::Student);
    $lesson = Lesson::factory()->published()->free()->create();
    $questions = Question::factory()->count(3)->for($lesson)->withOptions()->create();
    $correct = fn (Question $q) => $q->options()->where('is_correct', true)->value('id');
    $wrong = fn (Question $q) => $q->options()->where('is_correct', false)->value('id');

    // 1/3 = 33% → fail
    $this->actingAs($student)->post(route('lessons.quiz.submit', $lesson->slug), ['answers' => [
        $questions[0]->id => $correct($questions[0]),
        $questions[1]->id => $wrong($questions[1]),
        $questions[2]->id => $wrong($questions[2]),
    ]])->assertRedirect();
    expect(LessonProgress::where('user_id', $student->id)->first()?->completed_at)->toBeNull();

    // 3/3 → pass and complete
    $this->actingAs($student)->post(route('lessons.quiz.submit', $lesson->slug), ['answers' => $questions->mapWithKeys(fn ($q) => [$q->id => $correct($q)])->all()]);

    expect($student->quizAttempts()->count())->toBe(2)
        ->and($student->quizAttempts()->latest('id')->first()->score)->toBe(3)
        ->and(LessonProgress::where('user_id', $student->id)->first()->completed_at)->not->toBeNull();
});

it('blocks progress, quizzes and worksheets on premium lessons for free students', function () {
    $lesson = Lesson::factory()->published()->create(['pdf_path' => 'lesson-pdfs/x.pdf']);
    Question::factory()->for($lesson)->withOptions()->create();
    $student = userWithRole(RoleName::Student);

    $this->actingAs($student)->post(route('lessons.complete', $lesson->slug))->assertForbidden();
    $this->actingAs($student)->post(route('lessons.quiz.submit', $lesson->slug), ['answers' => [1 => 1]])->assertForbidden();
    $this->actingAs($student)->get(route('lessons.worksheet', $lesson->slug))->assertForbidden();
    $this->actingAs($student)->postJson(route('lessons.position', $lesson->slug), ['seconds' => 10])->assertForbidden();
});

it('tracks completion, favourites and video position', function () {
    $student = userWithRole(RoleName::Student);
    $lesson = Lesson::factory()->published()->free()->create();

    $this->actingAs($student)->post(route('lessons.complete', $lesson->slug));
    $this->actingAs($student)->post(route('lessons.favorite', $lesson->slug));
    $this->actingAs($student)->postJson(route('lessons.position', $lesson->slug), ['seconds' => 42])->assertOk();

    $progress = LessonProgress::firstWhere('user_id', $student->id);
    expect($progress->completed_at)->not->toBeNull()
        ->and($progress->favorited_at)->not->toBeNull()
        ->and($progress->last_position_seconds)->toBe(42);

    // Toggling again undoes it.
    $this->actingAs($student)->post(route('lessons.complete', $lesson->slug));
    expect($progress->fresh()->completed_at)->toBeNull();
});

it('downloads the worksheet for allowed users', function () {
    Storage::fake('local');
    Storage::disk('local')->put('lesson-pdfs/sheet.pdf', '%PDF-1.4');
    $lesson = Lesson::factory()->published()->free()->create(['pdf_path' => 'lesson-pdfs/sheet.pdf']);

    $this->actingAs(userWithRole(RoleName::Student))
        ->get(route('lessons.worksheet', $lesson->slug))
        ->assertOk()
        ->assertDownload("{$lesson->slug}.pdf");
});

it('shows a personal dashboard with progress per level', function () {
    $student = userWithRole(RoleName::Student);
    $level = Level::factory()->create(['code' => 'A1', 'position' => 1]);
    $done = Lesson::factory()->published()->free()->for($level)->create();
    Lesson::factory()->published()->free()->for($level)->create(['title' => 'Next one']);
    LessonProgress::create(['user_id' => $student->id, 'lesson_id' => $done->id, 'completed_at' => now()]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.completed', 1)
            ->where('stats.total', 2)
            ->where('levels.0.completed', 1)
            ->where('nextLesson.title', 'Next one'));
});

it('lets editors manage quiz questions', function () {
    $lesson = Lesson::factory()->create();
    $editor = userWithRole(RoleName::Editor);

    $this->actingAs($editor)->post(route('admin.lessons.questions.store', $lesson), [
        'prompt' => 'I ___ happy.',
        'options' => ['am', 'is', 'are'],
        'correct' => 0,
    ])->assertSessionHasNoErrors();

    $question = $lesson->questions()->with('options')->first();
    expect($question->options)->toHaveCount(3)
        ->and($question->options->firstWhere('is_correct', true)->text)->toBe('am');

    $this->actingAs($editor)->post(route('admin.lessons.questions.store', $lesson), [
        'prompt' => 'Bad', 'options' => ['only one'], 'correct' => 3,
    ])->assertSessionHasErrors(['options', 'correct']);

    $this->actingAs($editor)->delete(route('admin.lessons.questions.destroy', [$lesson, $question]))->assertRedirect();
    expect($lesson->questions()->count())->toBe(0);

    $this->actingAs(userWithRole(RoleName::Student))
        ->post(route('admin.lessons.questions.store', $lesson), ['prompt' => 'x'])
        ->assertForbidden();
});
