<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'role' => RoleName::tryFrom($request->string('role')->toString())?->value,
        ];

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")))
            ->when($filters['role'], fn (Builder $q, string $role) => $q->role($role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'is_current' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => collect(RoleName::cases())->map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('updateRoles', $user);

        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::enum(RoleName::class)],
        ]);

        $user->syncRoles($validated['roles']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated for :name.', ['name' => $user->name])]);

        return back();
    }
}
