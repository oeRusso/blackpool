<?php

namespace App\Enums;

enum VideoSource: string
{
    case Upload = 'upload';
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';

    public function isEmbed(): bool
    {
        return $this !== self::Upload;
    }
}
