<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Lesson;

beforeEach(fn () => $this->actingAs(userWithRole(RoleName::Editor)));

it('creates a category with a generated slug', function () {
    $this->post(route('admin.categories.store'), ['name' => 'Phrasal Verbs'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Category::firstWhere('name', 'Phrasal Verbs')->slug)->toBe('phrasal-verbs');
});

it('validates the category name', function (array $payload, string $error) {
    Category::factory()->create(['name' => 'Grammar']);

    $this->post(route('admin.categories.store'), $payload)->assertSessionHasErrors($error);
})->with([
    'missing' => [[], 'name'],
    'too long' => [['name' => str_repeat('a', 101)], 'name'],
    'duplicate' => [['name' => 'Grammar'], 'name'],
]);

it('allows keeping the same name when updating', function () {
    $category = Category::factory()->create(['name' => 'Grammar']);

    $this->put(route('admin.categories.update', $category), ['name' => 'Grammar', 'description' => 'Updated'])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->description)->toBe('Updated');
});

it('refuses to delete a category that still has lessons', function () {
    $category = Category::factory()->create();
    Lesson::factory()->create()->categories()->attach($category);

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

    expect($category->fresh())->not->toBeNull();
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

    expect($category->fresh())->toBeNull();
});
