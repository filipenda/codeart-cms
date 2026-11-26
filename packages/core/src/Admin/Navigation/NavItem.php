<?php

namespace Codeart\Core\Admin\Navigation;

/**
 * One entry of the admin sidebar. A GROUP has no route and holds children (declared in `children` or by pointing
 * their `parent` at its key); a LINK has a route name. Labels may be translation keys (`pages::menu.pages`).
 */
final readonly class NavItem
{
    /** @var list<NavItem> */
    public array $children;

    /**
     * @param  string  $key  unique, e.g. `pages` or `pages.list`
     * @param  string|null  $route  route name; null = a group
     * @param  string|null  $permission  Gate ability the user needs; null = everyone who may enter the admin
     * @param  string|null  $parent  key of the group this item sits in
     * @param  list<NavItem>  $children
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public ?string $route = null,
        public ?string $permission = null,
        public int $order = 100,
        public ?string $parent = null,
        array $children = [],
        public ?string $module = null,
    ) {
        $this->children = $children;
    }
}
