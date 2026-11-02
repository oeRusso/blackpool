<?php

use App\Http\Controllers\VideoStreamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::get('lessons/{lesson:slug}/video/{path}', VideoStreamController::class)
    ->where('path', 'master\.m3u8|thumbnail\.jpg|\d{3,4}p/(index\.m3u8|segment_\d{3,5}\.ts)')
    ->name('lessons.video.stream');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
