<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('status', 20)->index();
            // Embeds (YouTube/Vimeo)
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();
            $table->string('title')->nullable();
            $table->string('thumbnail_url')->nullable();
            // Uploads
            $table->string('disk')->nullable();
            $table->string('directory')->nullable();
            $table->string('original_name')->nullable();
            $table->string('original_path')->nullable();
            $table->string('playlist_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->json('renditions')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
