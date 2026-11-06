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

// Nightly backups on the VPS (database dump + uploaded files), see docs/DEPLOYMENT.md.
Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
Schedule::command('backup:run')->daily()->at('01:30')->onOneServer();
Schedule::command('backup:monitor')->daily()->at('09:00');

// Public demo: restore the seeded content every night.
Schedule::command('demo:reset')->dailyAt('03:00')->when(fn () => config('demo.enabled'));
