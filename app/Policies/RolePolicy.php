<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageRoles->value);
    }

    /**
     * The admin role always keeps every permission.
     */
    public function update(User $user, Role $role): Response
    {
        if (! $user->can(PermissionName::ManageRoles->value)) {
            return Response::deny();
        }

        return $role->name === RoleName::Admin->value
            ? Response::deny(__('The administrator role cannot be modified.'))
            : Response::allow();
    }
}
