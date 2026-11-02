<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageUsers->value);
    }

    /**
     * Users may not change their own roles, so an admin can never lock themselves out.
     */
    public function updateRoles(User $user, User $target): Response
    {
        if (! $user->can(PermissionName::ManageUsers->value)) {
            return Response::deny();
        }

        return $user->is($target)
            ? Response::deny(__('You cannot change your own roles.'))
            : Response::allow();
    }
}
