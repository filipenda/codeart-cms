<?php

namespace Codeart\Core\Branding;

use Codeart\Core\Images\ImageProcessor;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores logos and the favicon for `image` settings (see config `codeart.branding`).
 * Only png, jpg, webp, decided by the file CONTENT (never the client's extension or mime type).
 * SVG is refused on purpose (it can carry scripts). Files get random names; the old one is deleted.
 * PNG/WebP with transparent margins are trimmed by the ImageProcessor: the trimmed file is the one served (stored in
 * the setting), the untouched original stays next to it as `<name>.original.<ext>` so the trim is reversible.
 * Raw layer: no permission check (SettingsManager::uploadImage adds it).
 */
class BrandingImageUploader
{
    public function __construct(private readonly Settings $settings, private readonly ImageProcessor $images) {}

    /** @return string the stored path (relative to the branding disk) */
    public function store(string $key, UploadedFile $file): string
    {
        $definition = $this->settings->registry()->definition($key);
        if ($definition->type !== SettingType::Image) {
            throw new InvalidSettingValue($key, 'is not an image setting');
        }

        $extension = $this->validate($key, $file);

        $directory = $this->directory();
        $disk = Storage::disk($this->disk());
        $name = Str::random(40);
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $trimmed = $this->images->trimTransparent($bytes);

        if ($trimmed === null) {
            $path = $disk->putFileAs($directory, $file, $name.'.'.$extension);
        } else {
            $disk->put($directory.'/'.$name.'.original.'.$extension, $bytes);
            $path = $directory.'/'.$name.'.'.$extension;
            $disk->put($path, $trimmed);
        }
        if (! is_string($path) || ! $disk->exists($path)) {
            throw new InvalidSettingValue($key, 'the file could not be stored');
        }

        $old = $this->settings->has($key) ? $this->settings->get($key) : null;
        try {
            $this->settings->set($key, $path);
        } catch (\Throwable $e) {
            $this->delete($path);
            throw $e;
        }
        $this->deleteIfOurs($old, $path);

        return $path;
    }

    /** Remove the image: the file is deleted and the setting returns to its default. */
    public function remove(string $key): void
    {
        $old = $this->settings->has($key) ? $this->settings->get($key) : null;
        $this->settings->forget($key);
        $this->deleteIfOurs($old, null);
    }

    public function url(string $key): ?string
    {
        $path = $this->settings->get($key);

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Local files are linked root-relative: an absolute URL built from APP_URL breaks as soon as the
        // site is opened under another host (http://localhost vs the real domain), and the logo vanishes.
        $url = Storage::disk($this->disk())->url($path);
        $base = rtrim((string) config('app.url'), '/');
        if ($base !== '' && str_starts_with($url, $base.'/')) {
            return substr($url, strlen($base));
        }

        return $url;
    }

    /** @return string the extension for the detected type */
    private function validate(string $key, UploadedFile $file): string
    {
        $maxKb = (int) config('codeart.branding.max_kb', 2048);
        $maxSide = (int) config('codeart.branding.max_dimension', 4096);

        if (! $file->isValid() || ($file->getRealPath() === false)) {
            throw new InvalidSettingValue($key, 'the upload failed');
        }
        if ($file->getSize() > $maxKb * 1024) {
            throw new InvalidSettingValue($key, "the file is larger than {$maxKb} KB");
        }

        $info = $this->images->inspect((string) file_get_contents((string) $file->getRealPath()));
        $detected = $info?->mime;
        $extension = $info?->extension();
        // Two independent content checks must agree: PHP's image parser and libmagic.
        if ($info === null || $extension === null || $file->getMimeType() !== $detected) {
            throw new InvalidSettingValue($key, 'only PNG, JPG and WebP images are accepted');
        }
        if ($info->width > $maxSide || $info->height > $maxSide) {
            throw new InvalidSettingValue($key, "the image is larger than {$maxSide}x{$maxSide} pixels");
        }

        return $extension;
    }

    private function deleteIfOurs(mixed $old, ?string $current): void
    {
        if (is_string($old) && $old !== $current && str_starts_with($old, $this->directory().'/')) {
            $this->delete($old);
        }
    }

    /** Deletes the served file and the untouched original kept next to a trimmed one. */
    private function delete(string $path): void
    {
        $disk = Storage::disk($this->disk());
        $disk->delete($path);
        $disk->delete(preg_replace('/\.([a-z]+)$/', '.original.$1', $path) ?? $path);
    }

    private function disk(): string
    {
        return (string) config('codeart.branding.disk', 'public');
    }

    private function directory(): string
    {
        return trim((string) config('codeart.branding.directory', 'branding'), '/');
    }
}
