<?php

namespace App\Actions\Lessons;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Creates or updates a lesson with its categories and attached files.
 */
class SaveLesson
{
    public function __construct(private StoreLessonCover $storeCover) {}

    /**
     * @param  array<string, mixed>  $data  Validated LessonRequest data.
     */
    public function handle(array $data, User $author, ?Lesson $lesson = null): Lesson
    {
        $lesson ??= new Lesson(['author_id' => $author->id]);
        $oldFiles = [];

        $status = LessonStatus::from($data['status']);
        $publishedAt = $data['published_at'] ?? null;

        if ($status === LessonStatus::Published && $publishedAt === null) {
            $publishedAt = $lesson->published_at ?? now();
        }

        $lesson->fill([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'body' => $data['body'] ?? null,
            'level_id' => $data['level_id'],
            'is_free' => $data['is_free'] ?? false,
            'status' => $status,
            'published_at' => $status === LessonStatus::Published ? $publishedAt : null,
            'position' => $data['position'] ?? $lesson->position ?? 0,
        ]);

        if ($lesson->exists && $lesson->isDirty('title')) {
            $lesson->slug = Lesson::uniqueSlug($lesson->title, $lesson->id);
        }

        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $oldFiles[] = ['public', $lesson->cover_path];
            $lesson->cover_path = $this->storeCover->handle($data['cover']);
        } elseif ($data['remove_cover'] ?? false) {
            $oldFiles[] = ['public', $lesson->cover_path];
            $lesson->cover_path = null;
        }

        if (($data['pdf'] ?? null) instanceof UploadedFile) {
            $oldFiles[] = ['local', $lesson->pdf_path];
            $lesson->pdf_path = $data['pdf']->store('lesson-pdfs', 'local') ?: throw new RuntimeException('Could not store the PDF worksheet.');
        } elseif ($data['remove_pdf'] ?? false) {
            $oldFiles[] = ['local', $lesson->pdf_path];
            $lesson->pdf_path = null;
        }

        DB::transaction(function () use ($lesson, $data): void {
            $lesson->save();
            $lesson->categories()->sync($data['category_ids']);
        });

        foreach ($oldFiles as [$disk, $path]) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
        }

        return $lesson;
    }
}
