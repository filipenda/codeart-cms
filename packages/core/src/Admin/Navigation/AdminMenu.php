<?php

namespace Codeart\Core\Admin\Navigation;

use Codeart\Core\Modules\MenuItem;
use Codeart\Core\Modules\MenuRegistry;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;

/**
 * The admin sidebar. Modules register items from their service provider (`NavItem`); only ENABLED modules boot, so a
 * disabled module's items never exist. Items the user may not see, items whose route does not exist and groups left
 * empty are dropped. The `menu` entries of module.json (flat links) are included too.
 */
class AdminMenu
{
    /** @var array<string, NavItem> */
    private array $items = [];

    public function __construct(private readonly Gate $gate, private readonly Container $container) {}

    /** Add items (and their `children`). A later registration with the same key replaces the earlier one. */
    public function add(NavItem ...$items): void
    {
        foreach ($items as $item) {
            $this->items[$item->key] = $item;
            foreach ($item->children as $child) {
                $this->add(new NavItem($child->key, $child->label, $child->icon, $child->route, $child->permission, $child->order, $item->key, $child->children, $child->module ?? $item->module));
            }
        }
    }

    /** @return list<NavNode> the tree the user sees; $currentRoute = name of the current route (decides `active`) */
    public function tree(?Authenticatable $user, ?string $currentRoute = null): array
    {
        $all = $this->items + $this->manifestItems($user);

        $byParent = [];
        foreach ($all as $item) {
            $byParent[$item->parent !== null && isset($all[$item->parent]) ? $item->parent : ''][] = $item;
        }

        return $this->build($byParent, '', $user, $currentRoute);
    }

    /**
     * Visible links in menu order, for search: [label (with its group), url, icon].
     *
     * @return list<array{label: string, url: string, icon: string, group: ?string}>
     */
    public function links(?Authenticatable $user): array
    {
        $links = [];
        foreach ($this->tree($user) as $node) {
            foreach ($node->isGroup() ? $node->children : [$node] as $link) {
                $links[] = ['label' => $link->label, 'url' => (string) $link->url, 'icon' => $link->item->icon, 'group' => $node->isGroup() ? $node->label : null];
            }
        }

        return $links;
    }

    /**
     * @param  array<string, list<NavItem>>  $byParent
     * @return list<NavNode>
     */
    private function build(array $byParent, string $parent, ?Authenticatable $user, ?string $currentRoute): array
    {
        $items = $byParent[$parent] ?? [];
        usort($items, fn (NavItem $a, NavItem $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        $nodes = [];
        foreach ($items as $item) {
            if (! $this->allowed($item, $user)) {
                continue;
            }
            $children = $item->route === null ? $this->build($byParent, $item->key, $user, $currentRoute) : [];
            if ($item->route === null) {
                if ($children === []) {
                    continue; // an empty group is hidden
                }
            } elseif (! Route::has($item->route)) {
                continue; // never link to a page that does not exist
            }

            $nodes[] = new NavNode(
                $item,
                (string) __($item->label),
                $item->route !== null ? route($item->route) : null,
                $item->route !== null && self::isActive($item->route, $currentRoute),
                $children,
            );
        }

        return $nodes;
    }

    private function allowed(NavItem $item, ?Authenticatable $user): bool
    {
        if ($item->permission === null) {
            return true;
        }

        return $user !== null && $this->gate->forUser($user)->allows($item->permission);
    }

    /** The item's own route, or any route of its section when it ends in `.index` (`pages.index` covers `pages.edit`). */
    public static function isActive(string $route, ?string $current): bool
    {
        if ($current === null) {
            return false;
        }
        if ($current === $route) {
            return true;
        }

        return str_ends_with($route, '.index') && str_starts_with($current, substr($route, 0, -5).'.');
    }

    /** @return array<string, NavItem> */
    private function manifestItems(?Authenticatable $user): array
    {
        $items = [];
        foreach ($this->container->make(MenuRegistry::class)->items($user) as $entry) {
            /** @var MenuItem $entry */
            $key = $entry->module.'.'.$entry->route;
            $items[$key] = new NavItem($key, $entry->label, $entry->icon, $entry->route, $entry->permission, $entry->order, null, [], $entry->module);
        }

        return $items;
    }
}
