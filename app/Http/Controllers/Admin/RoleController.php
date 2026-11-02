<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::with('permissions:id,name')->withCount('users')->get()
            ->sortBy(fn (Role $role) => array_search(RoleName::tryFrom($role->name), RoleName::cases(), true))
            ->values()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => RoleName::tryFrom($role->name)?->label() ?? $role->name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name'),
                'locked' => $role->name === RoleName::Admin->value,
            ]);

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles,
            'permissions' => collect(PermissionName::cases())->map(fn (PermissionName $p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::enum(PermissionName::class)],
        ]);

        $role->syncPermissions($validated['permissions']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated for :role.', ['role' => $role->name])]);

        return back();
    }
}
