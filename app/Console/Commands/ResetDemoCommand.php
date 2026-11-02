<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Restores the public demo to its seeded state: content, uploads, visitor
 * accounts, sessions and the demo users' credentials.
 */
class ResetDemoCommand extends Command
{
    protected $signature = 'demo:reset {--force : Run even when DEMO_MODE is disabled}';

    protected $description = 'Reset the public demo to its initial content';

    private const CONTENT_TABLES = [
        'lesson_user', 'quiz_attempts', 'question_options', 'questions', 'videos',
        'category_lesson', 'lessons', 'categories', 'sessions', 'jobs', 'failed_jobs',
    ];

    public function handle(): int
    {
        if (! config('demo.enabled') && ! $this->option('force')) {
            $this->components->error('DEMO_MODE is disabled. Use --force to reset anyway.');

            return self::FAILURE;
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (self::CONTENT_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        });

        // Visitor accounts created during the day are removed; demo accounts are re-seeded.
        User::whereNotIn('email', config('demo.accounts'))->each(fn (User $user) => $user->delete());

        Storage::disk('public')->deleteDirectory('covers');
        Storage::disk('local')->deleteDirectory('lesson-pdfs');
        Storage::disk('local')->deleteDirectory('video-chunks');
        Storage::disk(config('media.disk'))->deleteDirectory('videos');

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->call('cache:clear');

        $this->components->info('Demo reset completed.');

        return self::SUCCESS;
    }
}
