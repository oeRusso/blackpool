<?php

use App\Enums\LessonStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function lessonPayload(array $overrides = []): array
{
    return [
        'title' => 'The Present Perfect',
        'summary' => 'Talk about experiences.',
        'body' => 'Long explanation',
        'level_id' => Level::factory()->create()->id,
        'category_ids' => [Category::factory()->create()->id],
        'is_free' => true,
        'status' => LessonStatus::Draft->value,
        ...$overrides,
    ];
}

it('creates a lesson with categories, author and slug', function () {
    $editor = userWithRole(RoleName::Editor);

    $this->actingAs($editor)->post(route('admin.lessons.store'), lessonPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $lesson = Lesson::firstWhere('title', 'The Present Perfect');

    expect($lesson->slug)->toBe('the-present-perfect')
        ->and($lesson->author_id)->toBe($editor->id)
        ->and($lesson->categories)->toHaveCount(1)
        ->and($lesson->status)->toBe(LessonStatus::Draft)
        ->and($lesson->is_free)->toBeTrue();
});

it('generates unique slugs', function () {
    Lesson::factory()->create(['title' => 'Same title']);

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), lessonPayload(['title' => 'Same title']));

    expect(Lesson::pluck('slug')->all())->toContain('same-title', 'same-title-2');
});

it('requires level, categories, title and summary', function () {
    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), ['status' => 'draft'])
        ->assertSessionHasErrors(['title', 'summary', 'level_id', 'category_ids']);
});

it('sets published_at when publishing', function () {
    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), lessonPayload(['status' => LessonStatus::Published->value]));

    expect(Lesson::first()->published_at)->not->toBeNull()
        ->and(Lesson::first()->isPublished())->toBeTrue();
});

it('blocks status changes for users without the publish permission', function () {
    Role::findByName(RoleName::Editor->value)->revokePermissionTo(PermissionName::PublishLessons->value);

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), lessonPayload(['status' => LessonStatus::Published->value]))
        ->assertSessionHasErrors('status');
});

it('stores the cover as webp and the pdf privately', function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), lessonPayload([
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
            'pdf' => UploadedFile::fake()->create('worksheet.pdf', 200, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $lesson = Lesson::first();

    expect($lesson->cover_path)->toEndWith('.webp')
        ->and($lesson->pdf_path)->toStartWith('lesson-pdfs/');
    Storage::disk('public')->assertExists($lesson->cover_path);
    Storage::disk('local')->assertExists($lesson->pdf_path);
    expect(getimagesizefromstring(Storage::disk('public')->get($lesson->cover_path))[0])->toBe(1280);
});

it('rejects non-pdf worksheets', function () {
    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.store'), lessonPayload([
            'pdf' => UploadedFile::fake()->create('virus.exe', 10),
        ]))
        ->assertSessionHasErrors('pdf');
});

it('updates and soft deletes a lesson', function () {
    $lesson = Lesson::factory()->create();
    $editor = userWithRole(RoleName::Editor);

    $this->actingAs($editor)->put(route('admin.lessons.update', $lesson), lessonPayload(['title' => 'Renamed lesson']))
        ->assertSessionHasNoErrors();
    expect($lesson->fresh()->slug)->toBe('renamed-lesson');

    $this->actingAs($editor)->delete(route('admin.lessons.destroy', $lesson))->assertRedirect(route('admin.lessons.index'));
    expect($lesson->fresh()->trashed())->toBeTrue();
});

it('renders the edit page with its video, quiz and upload limits', function () {
    $lesson = Lesson::factory()->create();
    Video::factory()->for($lesson)->create();
    Question::factory()->for($lesson)->withOptions()->create();

    $this->actingAs(userWithRole(RoleName::Editor))
        ->get(route('admin.lessons.edit', $lesson))
        ->assertInertia(fn ($page) => $page
            ->component('admin/lessons/Form')
            ->where('lesson.id', $lesson->id)
            ->where('video.source', 'youtube')
            ->has('questions', 1)
            ->has('questions.0.options', 3)
            ->has('uploadLimits.extensions')
            ->has('levels')
            ->has('categories'));
});

it('can run the demo seeders twice without duplicating content', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Lesson::count())->toBe(31)
        ->and(Question::count())->toBe(11)
        ->and(User::count())->toBe(4);
});
