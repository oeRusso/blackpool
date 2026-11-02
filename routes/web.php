<?php

use App\Http\Controllers\Learning\CatalogController;
use App\Http\Controllers\Learning\DashboardController;
use App\Http\Controllers\Learning\LessonController;
use App\Http\Controllers\Learning\ProgressController;
use App\Http\Controllers\VideoStreamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('lessons', CatalogController::class)->name('lessons.index');
Route::get('lessons/{lesson:slug}', [LessonController::class, 'show'])->name('lessons.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('lessons/{lesson:slug}/worksheet', [LessonController::class, 'worksheet'])->name('lessons.worksheet');
    Route::post('lessons/{lesson:slug}/quiz', [LessonController::class, 'submitQuiz'])->middleware('throttle:10,1')->name('lessons.quiz.submit');
    Route::post('lessons/{lesson:slug}/position', [ProgressController::class, 'position'])->middleware('throttle:30,1')->name('lessons.position');
    Route::post('lessons/{lesson:slug}/complete', [ProgressController::class, 'complete'])->name('lessons.complete');
    Route::post('lessons/{lesson:slug}/favorite', [ProgressController::class, 'favorite'])->name('lessons.favorite');
});

Route::get('lessons/{lesson:slug}/video/{path}', VideoStreamController::class)
    ->where('path', 'master\.m3u8|thumbnail\.jpg|\d{3,4}p/(index\.m3u8|segment_\d{3,5}\.ts)')
    ->name('lessons.video.stream');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
