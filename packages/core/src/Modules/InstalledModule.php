<?php

namespace Codeart\Core\Modules;

/** A module package found on disk: its manifest, where it lives and the Composer-installed version. */
final readonly class InstalledModule
{
    public function __construct(
        public ModuleManifest $manifest,
        public string $path,
        public string $version,
    ) {}
}
