<?php

namespace Codeart\Core\Admin\Search;

use Codeart\Core\Admin\Navigation\AdminMenu;
use Illuminate\Contracts\Auth\Authenticatable;

/** Every menu link the user may see. */
class MenuSearchProvider implements SearchProvider
{
    public function __construct(private readonly AdminMenu $menu) {}

    public function results(?Authenticatable $user): array
    {
        return array_map(
            fn (array $link): SearchResult => new SearchResult($link['label'], $link['url'], $link['icon'], $link['group']),
            $this->menu->links($user),
        );
    }
}
