<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin area. The `can:access-admin` gate keeps students out of the whole
| prefix, and every controller action authorizes again through its policy.
*/
Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('lessons', LessonController::class)->except(['show']);

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
