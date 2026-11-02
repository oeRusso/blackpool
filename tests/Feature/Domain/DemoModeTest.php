<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['demo.enabled' => true]);
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::firstWhere('email', 'admin@blackpool.test');
});

it('shares the demo accounts for one-click login', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('demo.password', 'password')
        ->where('demo.accounts.admin', 'admin@blackpool.test'));
});

it('does not share demo data when demo mode is off', function () {
    config(['demo.enabled' => false]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('demo', null));
});

it('stops demo accounts from changing credentials or security settings', function () {
    $this->actingAs($this->admin)
        ->from(route('profile.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('profile.edit'));

    $this->actingAs($this->admin)->patch(route('profile.update'), ['name' => 'Hacker', 'email' => 'x@example.com']);
    $this->actingAs($this->admin)->delete(route('profile.destroy'), ['password' => 'password']);
    $this->actingAs($this->admin)->postJson(route('two-factor.enable'))->assertForbidden();

    $this->admin->refresh();
    expect(Hash::check('password', $this->admin->password))->toBeTrue()
        ->and($this->admin->email)->toBe('admin@blackpool.test')
        ->and($this->admin->two_factor_secret)->toBeNull();
});

it('blocks password reset emails for demo accounts', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'admin@blackpool.test']);

    Notification::assertNothingSent();
});

it('still lets regular users manage their own account', function () {
    $user = userWithRole(RoleName::Student);

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Renamed');
});

it('resets the demo content, uploads and visitor accounts', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/visitor.webp', 'x');
    Category::create(['name' => 'Visitor category']);
    Lesson::first()->delete();
    User::factory()->create(['email' => 'visitor@example.com']);
    $this->admin->update(['name' => 'Changed by a visitor']);

    $this->artisan('demo:reset')->assertSuccessful();

    expect(Lesson::count())->toBe(31)
        ->and(Category::where('name', 'Visitor category')->exists())->toBeFalse()
        ->and(User::where('email', 'visitor@example.com')->exists())->toBeFalse()
        ->and(User::firstWhere('email', 'admin@blackpool.test')->name)->toBe('Ada Admin');
    Storage::disk('public')->assertMissing('covers/visitor.webp');
});

it('refuses to reset when demo mode is disabled', function () {
    config(['demo.enabled' => false]);

    $this->artisan('demo:reset')->assertFailed();
    expect(Lesson::count())->toBe(31);
});
