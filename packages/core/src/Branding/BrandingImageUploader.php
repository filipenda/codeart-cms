<?php

namespace Codeart\Core\Branding;

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
 * Raw layer: no permission check (SettingsManager::uploadImage adds it).
 */
class BrandingImageUploader
{
    /** @var array<string, string> detected mime => extension */
    private const array TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private readonly Settings $settings) {}

    /** @return string the stored path (relative to the branding disk) */
    public function store(string $key, UploadedFile $file): string
    {
        $definition = $this->settings->registry()->definition($key);
        if ($definition->type !== SettingType::Image) {
            throw new InvalidSettingValue($key, 'is not an image setting');
        }

        $extension = $this->validate($key, $file);

        $directory = $this->directory();
        $path = Storage::disk($this->disk())->putFileAs($directory, $file, Str::random(40).'.'.$extension);
        if (! is_string($path)) {
            throw new InvalidSettingValue($key, 'the file could not be stored');
        }

        $old = $this->settings->has($key) ? $this->settings->get($key) : null;
        try {
            $this->settings->set($key, $path);
        } catch (\Throwable $e) {
            Storage::disk($this->disk())->delete($path);
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

        return is_string($path) && $path !== '' ? Storage::disk($this->disk())->url($path) : null;
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

        $info = @getimagesize((string) $file->getRealPath());
        $detected = is_array($info) ? $info['mime'] : null;
        $extension = $detected !== null ? (self::TYPES[$detected] ?? null) : null;
        // Two independent content checks must agree: PHP's image parser and libmagic.
        if ($extension === null || $file->getMimeType() !== $detected) {
            throw new InvalidSettingValue($key, 'only PNG, JPG and WebP images are accepted');
        }
        if ($info[0] > $maxSide || $info[1] > $maxSide) {
            throw new InvalidSettingValue($key, "the image is larger than {$maxSide}x{$maxSide} pixels");
        }

        return $extension;
    }

    private function deleteIfOurs(mixed $old, ?string $current): void
    {
        if (is_string($old) && $old !== $current && str_starts_with($old, $this->directory().'/')) {
            Storage::disk($this->disk())->delete($old);
        }
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
