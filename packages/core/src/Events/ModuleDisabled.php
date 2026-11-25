<?php

namespace Codeart\Core\Events;

use Codeart\Core\Modules\ModuleManifest;

/** Fired after a module was disabled. Its data, tables and permissions are untouched. */
final readonly class ModuleDisabled
{
    public function __construct(public ModuleManifest $module) {}
}
