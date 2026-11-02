<?php

use App\Services\Video\ChunkedUploadManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ChunkedUploadManager::class)->prune())
    ->daily()
    ->name('prune-abandoned-video-uploads');
