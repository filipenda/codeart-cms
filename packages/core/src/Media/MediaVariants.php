<?php

namespace Codeart\Core\Media;

use Codeart\Core\Images\ImageFormat;
use Codeart\Core\Images\ImageProcessor;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pre-generated sizes (no on-the-fly route: many clients are on shared hosting). A size is written next to the original as
 * `<name>-<size>.<ext>` plus a WebP copy when the server can encode it. A failure of one size is reported and skipped:
 * the original always stays usable and `variantUrl()` falls back to it.
 */
final class MediaVariants
{
    public function __construct(private readonly ImageProcessor $images) {}

    /** @return array<string, int> name => width */
    public static function sizes(): array
    {
        $sizes = [];
        foreach ((array) config('codeart.media.variants', ['thumb' => 320, 'medium' => 800, 'large' => 1600]) as $name => $width) {
            if (is_string($name) && preg_match('/^[a-z0-9]{1,20}$/', $name) === 1 && (int) $width >= 16) {
                $sizes[$name] = (int) $width;
            }
        }

        return $sizes;
    }

    /** Writes the sizes of an image and saves the list on the model. Removes the old ones first. */
    public function generate(Media $media, ?string $bytes = null): void
    {
        $this->purge($media);
        if (! $media->isImage() || $media->width === null) {
            $media->forceFill(['variants' => null])->save();

            return;
        }

        $disk = Storage::disk($media->disk);
        try {
            $bytes ??= (string) $disk->get($media->path);
        } catch (Throwable $e) {
            report($e);

            return;
        }
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $media->path) ?? $media->path;
        $format = ImageFormat::fromMime($media->mime) ?? ImageFormat::Png; // a GIF gets png sizes (first frame)
        $webp = (bool) config('codeart.media.webp', true) && function_exists('imagewebp') && $format !== ImageFormat::Webp;
        $variants = [];

        foreach (self::sizes() as $name => $width) {
            // Never enlarge; but `thumb` always exists so lists have a small picture.
            if ($media->width <= $width && $name !== 'thumb') {
                continue;
            }
            try {
                $resized = $this->images->fit($bytes, $width, 10000);
                $info = $this->images->probe($resized);
                if ($info === null) {
                    continue;
                }
                $path = $base.'-'.$name.'.'.$format->value;
                $disk->put($path, $resized);
                $entry = ['path' => $path, 'width' => $info->width, 'height' => $info->height];
                if ($webp) {
                    try {
                        $webpPath = $base.'-'.$name.'.webp';
                        $disk->put($webpPath, $this->images->encode($resized, ImageFormat::Webp, 82));
                        $entry['webp'] = $webpPath;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                $variants[$name] = $entry;
            } catch (Throwable $e) {
                report($e); // this size is missing; the original still works
            }
        }

        $media->forceFill(['variants' => $variants === [] ? null : $variants])->save();
    }

    /** Deletes the generated files of a media (not the original). */
    public function purge(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        foreach ($media->variants ?? [] as $variant) {
            foreach (['path', 'webp'] as $key) {
                if (isset($variant[$key])) {
                    $disk->delete($variant[$key]);
                }
            }
        }
    }
}
