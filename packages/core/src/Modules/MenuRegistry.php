<?php

namespace Codeart\Core\Modules;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/** Menu items of ENABLED modules only, limited to what the user may see, sorted by order. */
class MenuRegistry
{
    public function __construct(private readonly ModuleRegistry $modules, private readonly Gate $gate) {}

    /** @return list<MenuItem> */
    public function items(?Authenticatable $user): array
    {
        $items = [];
        foreach ($this->modules->enabled() as $module) {
            foreach ($module->manifest->menu as $item) {
                if ($this->visible($item, $user)) {
                    $items[] = $item;
                }
            }
        }

        usort($items, fn (MenuItem $a, MenuItem $b): int => $a->order <=> $b->order);

        return $items;
    }

    private function visible(MenuItem $item, ?Authenticatable $user): bool
    {
        if ($item->permission === null) {
            return true;
        }

        return $user !== null && $this->gate->forUser($user)->allows($item->permission);
    }
}
