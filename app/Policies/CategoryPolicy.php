<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageCategories->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageCategories->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(PermissionName::ManageCategories->value);
    }

    /**
     * A category that still groups lessons cannot be deleted.
     */
    public function delete(User $user, Category $category): Response
    {
        if (! $user->can(PermissionName::ManageCategories->value)) {
            return Response::deny();
        }

        return $category->lessons()->exists()
            ? Response::deny(__('This category still has lessons. Remove them from the category first.'))
            : Response::allow();
    }
}
