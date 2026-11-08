<?php

namespace App\Actions\Lessons;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores a lesson cover on the public disk, resized and converted to WebP with GD.
 */
class StoreLessonCover
{
    public const MAX_WIDTH = 1280;

    public function handle(UploadedFile $file): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('The uploaded cover is not a valid image.');
        }

        if (imagesx($image) > self::MAX_WIDTH) {
            $scaled = imagescale($image, self::MAX_WIDTH);
            imagedestroy($image);
            $image = $scaled ?: throw new RuntimeException('Could not resize the cover.');
        }

        ob_start();
        imagewebp($image, null, 80);
        $webp = (string) ob_get_clean();
        imagedestroy($image);

        $path = 'covers/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }
}
