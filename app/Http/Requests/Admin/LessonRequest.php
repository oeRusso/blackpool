<?php

namespace App\Http\Requests\Admin;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->lesson();

        return $lesson
            ? $this->user()?->can('update', $lesson) ?? false
            : $this->user()?->can('create', Lesson::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_free' => $this->boolean('is_free'),
            'remove_cover' => $this->boolean('remove_cover'),
            'remove_pdf' => $this->boolean('remove_pdf'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'level_id' => ['required', 'integer', Rule::exists('levels', 'id')],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'is_free' => ['boolean'],
            'status' => ['required', Rule::enum(LessonStatus::class)],
            'published_at' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'cover' => ['nullable', File::image()->max('4mb')],
            'pdf' => ['nullable', File::types(['pdf'])->max('20mb')],
            'remove_cover' => ['boolean'],
            'remove_pdf' => ['boolean'],
        ];
    }

    /**
     * Only users allowed to publish may change the publication status.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $requested = LessonStatus::tryFrom((string) $this->input('status'));
                $current = $this->lesson() !== null ? $this->lesson()->status : LessonStatus::Draft;

                if ($requested !== null && $requested !== $current
                    && ! $this->user()?->can('publish', $this->lesson() ?? new Lesson)) {
                    $validator->errors()->add('status', __('You are not allowed to change the publication status.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'level_id' => __('level'),
            'category_ids' => __('categories'),
        ];
    }

    public function lesson(): ?Lesson
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson ? $lesson : null;
    }
}
