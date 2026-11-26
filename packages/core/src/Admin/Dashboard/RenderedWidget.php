<?php

namespace Codeart\Core\Admin\Dashboard;

/** A widget with its finished HTML (or its error card). */
final readonly class RenderedWidget
{
    public function __construct(public Widget $widget, public string $html, public bool $failed = false) {}
}
