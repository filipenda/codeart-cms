<?php

namespace Codeart\Core\Admin\Search;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A source of command palette results. The palette is filled when the page renders, so return a small, cheap list
 * (menu items, "create ..." shortcuts, settings pages). Only return what the user may open.
 */
interface SearchProvider
{
    /** @return list<SearchResult> */
    public function results(?Authenticatable $user): array;
}
