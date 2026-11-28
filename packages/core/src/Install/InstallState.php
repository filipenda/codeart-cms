<?php

namespace Codeart\Core\Install;

/**
 * "Installed" = the marker file exists (one cheap file check per request). The installer exists only where it is switched on
 * (`codeart.install.enabled`): client packages yes, core's own tests and the dev app no.
 */
class InstallState
{
    public function __construct(private readonly InstallPaths $paths) {}

    public static function enabled(): bool
    {
        return (bool) config('codeart.install.enabled');
    }

    public function installed(): bool
    {
        return is_file($this->paths->marker);
    }

    /** The wizard runs: switched on and not installed yet. */
    public function pending(): bool
    {
        return self::enabled() && ! $this->installed();
    }

    public function markInstalled(): void
    {
        $dir = dirname($this->paths->marker);
        is_dir($dir) || mkdir($dir, 0775, true);
        file_put_contents($this->paths->marker, gmdate('c')."\n");
    }

    public function clearMarker(): void
    {
        is_file($this->paths->marker) && unlink($this->paths->marker);
    }
}
