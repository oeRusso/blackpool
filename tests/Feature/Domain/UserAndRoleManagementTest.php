<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Spatie\Permission\Models\Role;

it('lets admins grant premium to a student', function () {
    $student = userWithRole(RoleName::Student);

    $this->actingAs(userWithRole(RoleName::Admin))
        ->put(route('admin.users.roles.update', $student), ['roles' => ['student', 'premium']])
        ->assertSessionHasNoErrors();

    expect($student->fresh()->hasRole('premium'))->toBeTrue();
});

it('prevents admins from changing their own roles', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)
        ->put(route('admin.users.roles.update', $admin), ['roles' => []])
        ->assertForbidden();

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('rejects unknown roles', function () {
    $this->actingAs(userWithRole(RoleName::Admin))
        ->put(route('admin.users.roles.update', userWithRole(RoleName::Student)), ['roles' => ['superuser']])
        ->assertSessionHasErrors('roles.0');
});

it('updates role permissions but never the admin role', function () {
    $admin = userWithRole(RoleName::Admin);
    $editor = Role::findByName('editor');

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $editor), ['permissions' => [PermissionName::AccessAdmin->value]])
        ->assertSessionHasNoErrors();
    expect($editor->fresh()->permissions->pluck('name')->all())->toBe([PermissionName::AccessAdmin->value]);

    $this->actingAs($admin)
        ->put(route('admin.roles.update', Role::findByName('admin')), ['permissions' => []])
        ->assertForbidden();
});
