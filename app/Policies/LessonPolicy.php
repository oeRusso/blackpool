<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    /**
     * Admin listing of lessons.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageLessons->value);
    }

    /**
     * Whether the lesson page (metadata) can be seen at all.
     * Published lessons are public; drafts only for staff.
     */
    public function view(?User $user, Lesson $lesson): bool
    {
        return $lesson->isPublished()
            || ($user?->can(PermissionName::ManageLessons->value) ?? false);
    }

    /**
     * Whether the lesson content (video, PDF, quiz) can be consumed.
     * Free lessons require an account; premium lessons require the premium permission.
     */
    public function watch(?User $user, Lesson $lesson): Response
    {
        if ($user === null) {
            return Response::deny(__('Create a free account to watch this lesson.'));
        }

        if (! $this->view($user, $lesson)) {
            return Response::denyAsNotFound();
        }

        if ($lesson->is_free || $user->can(PermissionName::ViewPremiumLessons->value)) {
            return Response::allow();
        }

        return Response::deny(__('This is a premium lesson.'));
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageLessons->value);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::ManageLessons->value);
    }

    public function publish(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::PublishLessons->value);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::ManageLessons->value);
    }
}
