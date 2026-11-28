<?php

namespace Codeart\Core\Profile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Where avatars live and how they are linked (root-relative, like the branding images). */
final class Avatar
{
    public static function disk(): string
    {
        return (string) config('codeart.branding.disk', 'public');
    }

    public static function directory(): string
    {
        return trim((string) config('codeart.avatars.directory', 'avatars'), '/');
    }

    /** Public URL of the user's avatar, or null (initials are shown then). */
    public static function url(?Model $user): ?string
    {
        $path = $user?->getAttribute('avatar_path');
        if (! is_string($path) || $path === '' || ! str_starts_with($path, self::directory().'/')) {
            return null;
        }

        $url = Storage::disk(self::disk())->url($path);
        $base = rtrim((string) config('app.url'), '/');

        return $base !== '' && str_starts_with($url, $base.'/') ? substr($url, strlen($base)) : $url;
    }
}
