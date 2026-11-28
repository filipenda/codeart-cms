<?php

namespace Codeart\Core\Profile;

use Codeart\Core\Images\ImageFormat;
use Codeart\Core\Images\ImageProcessor;
use Codeart\Core\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a user's own avatar: png/jpg/webp only (decided by the content, SVG never), fitted within 512x512, random file
 * name, the previous file deleted. Raw layer: the controller guarantees it is only ever called for the signed-in user.
 */
final class AvatarUploader
{
    public const int SIDE = 512;

    public function __construct(private readonly ImageProcessor $images, private readonly Settings $settings) {}

    public function maxKb(): int
    {
        return (int) $this->settings->get('profile.avatar_max_kb');
    }

    public function store(Model $user, UploadedFile $file): string
    {
        if (! $file->isValid() || $file->getRealPath() === false) {
            throw $this->fail(__('codeart::profile.avatar_failed'));
        }
        if ($file->getSize() > $this->maxKb() * 1024) {
            throw $this->fail(__('codeart::profile.avatar_too_large', ['mb' => round($this->maxKb() / 1024, 1)]));
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $info = $this->images->inspect($bytes);
        $format = $info === null ? null : ImageFormat::fromMime($info->mime);
        // Two independent content checks must agree: the image parser and libmagic.
        if ($info === null || $format === null || $file->getMimeType() !== $info->mime) {
            throw $this->fail(__('codeart::profile.avatar_type'));
        }

        $fitted = $this->images->encode($this->images->fit($bytes, self::SIDE, self::SIDE), $format);
        $disk = Storage::disk(Avatar::disk());
        $path = Avatar::directory().'/'.Str::random(40).'.'.$format->value;
        $disk->put($path, $fitted);

        $old = $user->getAttribute('avatar_path');
        $user->forceFill(['avatar_path' => $path])->saveQuietly();
        $this->deleteOld($old, $path);

        return $path;
    }

    public function remove(Model $user): void
    {
        $old = $user->getAttribute('avatar_path');
        $user->forceFill(['avatar_path' => null])->saveQuietly();
        $this->deleteOld($old, null);
    }

    private function deleteOld(mixed $old, ?string $current): void
    {
        if (is_string($old) && $old !== $current && str_starts_with($old, Avatar::directory().'/')) {
            Storage::disk(Avatar::disk())->delete($old);
        }
    }

    private function fail(string $message): ValidationException
    {
        return ValidationException::withMessages(['avatar' => $message]);
    }
}
