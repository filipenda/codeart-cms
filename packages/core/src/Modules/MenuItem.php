<?php

namespace Codeart\Core\Modules;

final readonly class MenuItem
{
    public function __construct(
        public string $label,
        public string $route,
        public ?string $permission,
        public string $icon,
        public int $order,
        public string $module,
    ) {}
}
