<?php

namespace Codeart\Core\Admin\Navigation;

/** A menu item as the sidebar renders it: already filtered, labelled and marked active. */
final readonly class NavNode
{
    /** @param  list<NavNode>  $children */
    public function __construct(
        public NavItem $item,
        public string $label,
        public ?string $url,
        public bool $active,
        public array $children = [],
    ) {}

    public function isGroup(): bool
    {
        return $this->item->route === null;
    }

    /** A group is open while one of its children is the current page. */
    public function open(): bool
    {
        foreach ($this->children as $child) {
            if ($child->active) {
                return true;
            }
        }

        return false;
    }
}
