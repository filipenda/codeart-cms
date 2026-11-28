<?php

namespace Codeart\Core\Images;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Intervention Image v3 behind {@see ImageProcessor}.
 * Intervention's own trim() compares colours with the corner pixel; it does not look at alpha, so the transparent
 * margin scan is done by {@see AlphaBounds} and Intervention only crops.
 */
class InterventionImageProcessor implements ImageProcessor
{
    /** Below this many pixels on a side a trimmed result is considered a mistake and the original stays. */
    private const int MIN_SIDE = 4;

    private readonly string $driver;

    private readonly ImageManager $manager;

    public function __construct(string $preferredDriver = 'gd', private readonly int $alphaTolerance = 8)
    {
        $this->driver = self::resolveDriver($preferredDriver);
        $this->manager = new ImageManager($this->driver === 'imagick' ? new ImagickDriver : new GdDriver);
    }

    /** `imagick` only when the extension exists; otherwise gd (and imagick as the last resort when gd is missing). */
    public static function resolveDriver(string $preferred): string
    {
        $hasGd = extension_loaded('gd');
        $hasImagick = extension_loaded('imagick');

        if ($preferred === 'imagick' && $hasImagick) {
            return 'imagick';
        }

        return $hasGd || ! $hasImagick ? 'gd' : 'imagick';
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function inspect(string $bytes): ?ImageInfo
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ImageFormat::fromMime($info['mime']) === null || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return new ImageInfo($info['mime'], $info[0], $info[1]);
    }

    public function probe(string $bytes): ?ImageInfo
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return new ImageInfo($info['mime'], $info[0], $info[1]);
    }

    public function stripMetadata(string $bytes): string
    {
        $info = $this->probe($bytes);
        $format = $info === null ? null : ImageFormat::fromMime($info->mime);
        if ($format === null) {
            return $bytes; // gif (or not an image): untouched
        }

        return $this->render($this->manager->read($bytes), $format, 92);
    }

    public function trimTransparent(string $bytes): ?string
    {
        $info = $this->inspect($bytes);
        if ($info === null || ! $info->canHaveAlpha() || ! extension_loaded('gd')) {
            return null;
        }

        $box = AlphaBounds::of($bytes, $this->alphaTolerance);
        if ($box === null) {
            return null; // fully transparent: nothing sensible to keep
        }
        if ($box['width'] === $info->width && $box['height'] === $info->height) {
            return null; // nothing to cut
        }
        if ($box['width'] < self::MIN_SIDE || $box['height'] < self::MIN_SIDE) {
            return null;
        }

        $image = $this->manager->read($bytes);
        $image->crop($box['width'], $box['height'], $box['x'], $box['y']);
        $format = ImageFormat::fromMime($info->mime) ?? ImageFormat::Png;

        // webp is re-encoded at 100 so the logo does not get softer; png is lossless.
        return $this->render($image, $format, 100);
    }

    public function fit(string $bytes, int $maxWidth, int $maxHeight): string
    {
        $info = $this->inspect($bytes);
        $image = $this->manager->read($bytes);
        $image->scaleDown($maxWidth, $maxHeight);

        return $this->render($image, ImageFormat::fromMime($info->mime ?? '') ?? ImageFormat::Png, 90);
    }

    public function encode(string $bytes, ImageFormat $format, int $quality = 90): string
    {
        return $this->render($this->manager->read($bytes), $format, $quality);
    }

    private function render(ImageInterface $image, ImageFormat $format, int $quality): string
    {
        $encoded = match ($format) {
            ImageFormat::Png => $image->toPng(),
            ImageFormat::Jpg => $image->toJpeg($quality),
            ImageFormat::Webp => $image->toWebp($quality),
        };

        return $encoded->toString();
    }
}
