<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            // A level can never be deleted while it still has lessons.
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->string('summary', 255);
            $table->text('body')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_free')->default(false)->index();
            $table->string('cover_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['level_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
