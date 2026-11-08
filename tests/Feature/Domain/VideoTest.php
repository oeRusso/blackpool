<?php

use App\Enums\RoleName;
use App\Enums\VideoSource;
use App\Enums\VideoStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Lesson;
use App\Models\Video;
use App\Services\Video\ChunkedUploadManager;
use App\Services\Video\EmbedResolver;
use App\Services\Video\HlsTranscoder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('media');
    config(['media.uploads.chunk_size' => 1024]);
});

it('parses youtube and vimeo urls', function (string $url, ?VideoSource $source, ?string $id) {
    $parsed = app(EmbedResolver::class)->parse($url);

    expect($parsed['source'] ?? null)->toBe($source)
        ->and($parsed['id'] ?? null)->toBe($id);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', VideoSource::YouTube, 'dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ?t=42', VideoSource::YouTube, 'dQw4w9WgXcQ'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ', VideoSource::YouTube, 'dQw4w9WgXcQ'],
    ['https://vimeo.com/76979871', VideoSource::Vimeo, '76979871'],
    ['https://player.vimeo.com/video/76979871', VideoSource::Vimeo, '76979871'],
    ['https://example.com/video.mp4', null, null],
]);

it('links an embedded video after validating it with oEmbed', function () {
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Present perfect', 'thumbnail_url' => 'https://i.ytimg.com/x.jpg'])]);
    $lesson = Lesson::factory()->create();

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.video.embed', $lesson), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertSessionHasNoErrors();

    expect($lesson->video)->source->toBe(VideoSource::YouTube)
        ->external_id->toBe('dQw4w9WgXcQ')
        ->title->toBe('Present perfect')
        ->status->toBe(VideoStatus::Ready);
});

it('rejects private or unknown embeds', function () {
    Http::fake(['www.youtube.com/oembed*' => Http::response(null, 404)]);

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.video.embed', Lesson::factory()->create()), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertSessionHasErrors('url');

    $this->actingAs(userWithRole(RoleName::Editor))
        ->post(route('admin.lessons.video.embed', Lesson::factory()->create()), ['url' => 'https://example.com/movie.mp4'])
        ->assertSessionHasErrors('url');
});

it('uploads a video in chunks and queues the transcoding job', function () {
    Queue::fake();
    $lesson = Lesson::factory()->create();
    $editor = userWithRole(RoleName::Editor);
    $content = random_bytes(2500); // 3 chunks of 1024 bytes

    $start = $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.start', $lesson), ['filename' => 'lesson.mp4', 'size' => 2500])
        ->assertCreated()
        ->assertJson(['chunk_size' => 1024, 'total_chunks' => 3]);

    $id = $start->json('id');

    foreach (str_split($content, 1024) as $index => $chunk) {
        $this->actingAs($editor)
            ->call('PUT', route('admin.lessons.video.uploads.chunk', [$lesson, $id, $index]), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $chunk)
            ->assertOk();
    }

    $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.complete', [$lesson, $id]))
        ->assertCreated()
        ->assertJsonPath('video.status', 'processing');

    $video = $lesson->fresh()->video;
    expect($video->source)->toBe(VideoSource::Upload)
        ->and(Storage::disk('media')->get($video->original_path))->toBe($content);
    Storage::disk('local')->assertMissing("video-chunks/{$id}");
    Queue::assertPushedOn('media', ProcessUploadedVideo::class);
});

it('refuses to complete an upload with missing chunks', function () {
    $lesson = Lesson::factory()->create();
    $editor = userWithRole(RoleName::Editor);
    $id = $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.start', $lesson), ['filename' => 'lesson.mp4', 'size' => 2500])
        ->json('id');

    $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.complete', [$lesson, $id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('rejects chunks of the wrong size and unsupported formats', function () {
    $lesson = Lesson::factory()->create();
    $editor = userWithRole(RoleName::Editor);

    $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.start', $lesson), ['filename' => 'malware.exe', 'size' => 10])
        ->assertJsonValidationErrors('filename');

    $id = $this->actingAs($editor)
        ->postJson(route('admin.lessons.video.uploads.start', $lesson), ['filename' => 'lesson.mp4', 'size' => 2500])
        ->json('id');

    $this->actingAs($editor)
        ->call('PUT', route('admin.lessons.video.uploads.chunk', [$lesson, $id, 0]), [], [], [], [], 'too short')
        ->assertStatus(422);
});

it('does not let students upload videos', function () {
    $this->actingAs(userWithRole(RoleName::Student))
        ->postJson(route('admin.lessons.video.uploads.start', Lesson::factory()->create()), ['filename' => 'a.mp4', 'size' => 10])
        ->assertForbidden();
});

it('marks the video as failed when processing fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Invalid data found', exitCode: 1)]);
    $video = Video::factory()->create([
        'source' => VideoSource::Upload, 'status' => VideoStatus::Processing,
        'disk' => 'media', 'directory' => 'videos/1/x', 'original_path' => 'videos/1/x/original.mp4',
    ]);

    try {
        (new ProcessUploadedVideo($video))->handle(app(HlsTranscoder::class));
    } catch (RuntimeException $exception) {
        (new ProcessUploadedVideo($video))->failed($exception);
    }

    expect($video->fresh())->status->toBe(VideoStatus::Failed)
        ->error->toContain('Invalid data found');
});

it('streams uploaded videos only to users allowed to watch', function () {
    $premiumLesson = Lesson::factory()->published()->create();
    $video = Video::factory()->for($premiumLesson)->create([
        'source' => VideoSource::Upload, 'disk' => 'media', 'directory' => 'videos/9/abc',
        'playlist_path' => 'videos/9/abc/hls/master.m3u8', 'thumbnail_path' => 'videos/9/abc/thumbnail.jpg',
    ]);
    Storage::disk('media')->put('videos/9/abc/hls/master.m3u8', "#EXTM3U\n");
    Storage::disk('media')->put('videos/9/abc/thumbnail.jpg', 'jpg');
    $url = route('lessons.video.stream', [$premiumLesson->slug, 'master.m3u8']);

    $this->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Student))->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Premium))->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');

    $premiumLesson->update(['is_free' => true]);
    $this->actingAs(userWithRole(RoleName::Student))->get($url)->assertOk();

    // Thumbnails are public previews.
    auth()->logout();
    $this->get(route('lessons.video.stream', [$premiumLesson->slug, 'thumbnail.jpg']))->assertOk();

    // Path traversal is rejected by the route constraint.
    $this->get("/lessons/{$premiumLesson->slug}/video/..%2F..%2F.env")->assertNotFound();
});

it('uses X-Accel-Redirect when configured', function () {
    config(['media.accel_redirect_prefix' => '/protected-media']);
    $lesson = Lesson::factory()->published()->free()->create();
    Video::factory()->for($lesson)->create(['source' => VideoSource::Upload, 'disk' => 'media', 'directory' => 'videos/1/a']);
    Storage::disk('media')->put('videos/1/a/hls/360p/index.m3u8', '#EXTM3U');

    $this->actingAs(userWithRole(RoleName::Student))
        ->get(route('lessons.video.stream', [$lesson->slug, '360p/index.m3u8']))
        ->assertHeader('X-Accel-Redirect', '/protected-media/videos/1/a/hls/360p/index.m3u8');
});

it('deletes stored files when a video is removed', function () {
    $lesson = Lesson::factory()->create();
    Video::factory()->for($lesson)->create(['source' => VideoSource::Upload, 'disk' => 'media', 'directory' => 'videos/5/z']);
    Storage::disk('media')->put('videos/5/z/hls/master.m3u8', 'x');

    $this->actingAs(userWithRole(RoleName::Editor))
        ->delete(route('admin.lessons.video.destroy', $lesson))
        ->assertRedirect();

    expect($lesson->fresh()->video)->toBeNull();
    Storage::disk('media')->assertMissing('videos/5/z/hls/master.m3u8');
});

it('transcodes a real video to HLS with ffmpeg', function () {
    Storage::fake('media');
    $disk = Storage::disk('media');
    $disk->makeDirectory('videos/1/real');
    $source = $disk->path('videos/1/real/original.mp4');
    exec('ffmpeg -hide_banner -loglevel error -f lavfi -i testsrc=duration=3:size=640x360:rate=25 -f lavfi -i sine=duration=3 -shortest -c:v libx264 -c:a aac -y '.escapeshellarg($source), result_code: $code);
    expect($code)->toBe(0);

    $video = Video::factory()->create([
        'source' => VideoSource::Upload, 'status' => VideoStatus::Processing,
        'disk' => 'media', 'directory' => 'videos/1/real', 'original_path' => 'videos/1/real/original.mp4',
    ]);

    ProcessUploadedVideo::dispatchSync($video);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Ready)
        ->and($video->renditions)->toBe(['360p'])
        ->and($video->duration_seconds)->toBe(3)
        ->and($video->lesson->fresh()->duration_seconds)->toBe(3);
    $disk->assertExists('videos/1/real/hls/master.m3u8');
    $disk->assertExists('videos/1/real/hls/360p/index.m3u8');
    $disk->assertExists('videos/1/real/thumbnail.jpg');
    $disk->assertMissing('videos/1/real/original.mp4');
})->group('ffmpeg');

it('prunes abandoned chunk folders', function () {
    Storage::disk('local')->put('video-chunks/old/0.part', 'x');
    Storage::disk('local')->put('video-chunks/fresh/0.part', 'x');
    touch(Storage::disk('local')->path('video-chunks/old'), now()->subDays(2)->getTimestamp());

    expect(app(ChunkedUploadManager::class)->prune())->toBe(1);
    Storage::disk('local')->assertMissing('video-chunks/old/0.part');
    Storage::disk('local')->assertExists('video-chunks/fresh/0.part');
});

it('never lets the queue retry a transcode that is still running', function (string $connection) {
    // retry_after must exceed the job timeout, otherwise a second worker would pick
    // up a long ffmpeg job while the first one is still processing it.
    $timeout = (new ProcessUploadedVideo(new Video))->timeout;

    expect(config("queue.connections.{$connection}.retry_after"))->toBeGreaterThan($timeout);
})->with(['database', 'redis']);
