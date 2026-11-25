<?php

namespace Codeart\Core\Events;

use Codeart\Core\Modules\ModuleManifest;

/** Fired after a module was enabled (migrated, permissions synced, booted). */
final readonly class ModuleEnabled
{
    public function __construct(public ModuleManifest $module, public string $version) {}
}
