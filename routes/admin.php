<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonVideoController;
use App\Http\Controllers\Admin\QuestionController;
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

        Route::controller(LessonVideoController::class)->prefix('lessons/{lesson}/video')->name('lessons.video.')->group(function () {
            Route::post('embed', 'embed')->name('embed');
            Route::post('uploads', 'startUpload')->middleware('throttle:20,1')->name('uploads.start');
            Route::put('uploads/{upload}/chunks/{index}', 'uploadChunk')->whereUuid('upload')->whereNumber('index')->name('uploads.chunk');
            Route::post('uploads/{upload}/complete', 'completeUpload')->whereUuid('upload')->name('uploads.complete');
            Route::delete('/', 'destroy')->name('destroy');
        });

        Route::post('lessons/{lesson}/questions', [QuestionController::class, 'store'])->name('lessons.questions.store');
        Route::delete('lessons/{lesson}/questions/{question}', [QuestionController::class, 'destroy'])->name('lessons.questions.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
