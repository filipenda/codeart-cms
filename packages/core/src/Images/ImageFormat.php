<?php

namespace Codeart\Core\Images;

enum ImageFormat: string
{
    case Png = 'png';
    case Jpg = 'jpg';
    case Webp = 'webp';

    public static function fromMime(string $mime): ?self
    {
        return match ($mime) {
            'image/png' => self::Png,
            'image/jpeg' => self::Jpg,
            'image/webp' => self::Webp,
            default => null,
        };
    }
}
