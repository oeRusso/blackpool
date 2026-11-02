<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VideoSource;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Lesson;
use App\Models\Video;
use App\Services\Video\ChunkedUploadManager;
use App\Services\Video\EmbedResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LessonVideoController extends Controller
{
    public function __construct(private ChunkedUploadManager $uploads) {}

    /**
     * Attach a YouTube or Vimeo video.
     */
    public function embed(Request $request, Lesson $lesson, EmbedResolver $resolver): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $validated = $request->validate(['url' => ['required', 'url:http,https', 'max:255']]);
        $embed = $resolver->resolve($validated['url']);

        $this->replaceVideo($lesson, [
            'source' => $embed['source'],
            'status' => VideoStatus::Ready,
            'external_id' => $embed['id'],
            'external_url' => $embed['url'],
            'title' => $embed['title'],
            'thumbnail_url' => $embed['thumbnail_url'],
            'processed_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Video linked.')]);

        return back();
    }

    /**
     * Begin a chunked upload and return the upload id and chunk size to the client.
     */
    public function startUpload(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('update', $lesson);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:200', function (string $attribute, string $value, \Closure $fail) {
                $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                if (! in_array($extension, config('media.uploads.extensions'), true)) {
                    $fail(__('Allowed formats: :formats.', ['formats' => implode(', ', config('media.uploads.extensions'))]));
                }
            }],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('media.uploads.max_size')],
        ], [
            'size.max' => __('The video may not be larger than :size MB.', ['size' => intdiv((int) config('media.uploads.max_size'), 1024 * 1024)]),
        ]);

        return response()->json(
            $this->uploads->start($lesson, $request->user(), $validated['filename'], (int) $validated['size']),
            201,
        );
    }

    /**
     * Receive one chunk as the raw request body.
     */
    public function uploadChunk(Request $request, Lesson $lesson, string $upload, int $index): JsonResponse
    {
        Gate::authorize('update', $lesson);

        $state = $this->uploads->find($upload, $lesson, $request->user());
        $this->uploads->storeChunk($state, $index, $request->getContent(true));

        return response()->json(['received' => $index]);
    }

    /**
     * Assemble the chunks and queue the transcoding job.
     */
    public function completeUpload(Request $request, Lesson $lesson, string $upload): JsonResponse
    {
        Gate::authorize('update', $lesson);

        $state = $this->uploads->find($upload, $lesson, $request->user());
        $file = $this->uploads->assemble($state);

        $video = $this->replaceVideo($lesson, [
            'source' => VideoSource::Upload,
            'status' => VideoStatus::Processing,
            'disk' => config('media.disk'),
            'directory' => $file['directory'],
            'original_name' => $state['filename'],
            'original_path' => $file['path'],
            'size_bytes' => $file['size'],
        ]);

        ProcessUploadedVideo::dispatch($video);

        return response()->json(['video' => $video->fresh()?->toPlayerArray()], 201);
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $lesson->video?->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Video removed.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function replaceVideo(Lesson $lesson, array $attributes): Video
    {
        return DB::transaction(function () use ($lesson, $attributes): Video {
            $lesson->video?->delete();

            return $lesson->video()->create($attributes);
        });
    }
}
