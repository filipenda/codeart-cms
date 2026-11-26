<?php

namespace Codeart\Core\Admin;

use Composer\InstalledVersions;

/** The installed core version, from Composer's metadata (the same source modules use). */
final class Version
{
    /** A tagged release: 1.2.3, v1.2.3, 1.2.3-rc.1 (Composer's pretty version of a tag). */
    private const string RELEASE = '/^v?\d+\.\d+\.\d+(?:-(?:alpha|beta|rc|RC)\.?\d*)?$/';

    /** "v0.3.0" for a tagged release, otherwise just "dev" (branches, dev-*, *-dev, unknown). */
    public static function cms(): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion('codeart/core');
        } catch (\Throwable) {
            $version = null;
        }

        return self::resolve($version);
    }

    public static function resolve(?string $version): string
    {
        $version = trim((string) $version);

        return preg_match(self::RELEASE, $version) === 1 ? 'v'.ltrim($version, 'vV') : 'dev';
    }
}
