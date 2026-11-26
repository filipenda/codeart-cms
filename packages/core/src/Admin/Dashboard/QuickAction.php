<?php

namespace Codeart\Core\Admin\Dashboard;

/** A shortcut shown in the "Quick actions" widget. */
final readonly class QuickAction
{
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $route,
        public ?string $permission = null,
        public int $order = 100,
    ) {}
}
