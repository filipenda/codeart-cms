<?php

namespace Codeart\Core\Admin;

use Composer\InstalledVersions;

/** The installed core version, from Composer's metadata (the same source modules use). */
final class Version
{
    public static function cms(): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion('codeart/core');
        } catch (\Throwable) {
            $version = null;
        }

        return ltrim($version ?? 'dev', 'vV');
    }
}
