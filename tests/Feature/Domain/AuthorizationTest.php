<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
| Regression suite for the legacy bug where permissions were only enforced in
| the UI: every admin endpoint must be authorized on the server.
*/

dataset('admin pages', [
    'dashboard' => fn () => route('admin.dashboard'),
    'categories' => fn () => route('admin.categories.index'),
    'lessons' => fn () => route('admin.lessons.index'),
    'lesson create' => fn () => route('admin.lessons.create'),
    'users' => fn () => route('admin.users.index'),
    'roles' => fn () => route('admin.roles.index'),
]);

it('redirects guests away from the admin area', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with('admin pages');

it('forbids students and premium students from the admin area', function (RoleName $role, string $url) {
    $this->actingAs(userWithRole($role))->get($url)->assertForbidden();
})->with([RoleName::Student, RoleName::Premium])->with('admin pages');

it('lets admins open every admin page', function (string $url) {
    $this->actingAs(userWithRole(RoleName::Admin))->get($url)->assertOk();
})->with('admin pages');

it('lets editors manage content but not users or roles', function () {
    $editor = userWithRole(RoleName::Editor);

    $this->actingAs($editor)->get(route('admin.categories.index'))->assertOk();
    $this->actingAs($editor)->get(route('admin.lessons.index'))->assertOk();
    $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.roles.index'))->assertForbidden();
});

it('rejects direct write requests from students', function () {
    $student = userWithRole(RoleName::Student);
    $category = Category::factory()->create();
    $lesson = Lesson::factory()->create();

    $this->actingAs($student)->post(route('admin.categories.store'), ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($student)->put(route('admin.categories.update', $category), ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($student)->delete(route('admin.categories.destroy', $category))->assertForbidden();
    $this->actingAs($student)->delete(route('admin.lessons.destroy', $lesson))->assertForbidden();

    expect(Category::where('name', 'Hacked')->exists())->toBeFalse()
        ->and($category->fresh())->not->toBeNull()
        ->and($lesson->fresh()->trashed())->toBeFalse();
});

it('assigns the student role to self-registered users', function () {
    $this->post(route('register.store'), [
        'name' => 'New Learner',
        'email' => 'learner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'learner@example.com')->first()->hasRole(RoleName::Student->value))->toBeTrue();
});

it('shares a server-computed ability map with the frontend', function () {
    $this->actingAs(userWithRole(RoleName::Editor))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.can.accessAdmin', true)
            ->where('auth.can.manageLessons', true)
            ->where('auth.can.manageUsers', false)
            ->where('auth.roles', ['editor']));
});

it('keeps levels from being deleted while they have lessons', function () {
    $level = Level::factory()->create();
    Lesson::factory()->for($level)->create();

    expect(fn () => $level->delete())->toThrow(QueryException::class);
});
