<?php

namespace Codeart\Core\Admin\Search;

/** One line of the command palette. `group` is the small caption ("Content", "Users" ...). */
final readonly class SearchResult
{
    public function __construct(
        public string $label,
        public string $url,
        public string $icon = 'search',
        public ?string $group = null,
    ) {}

    /** @return array{label: string, url: string, icon: string, group: ?string} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'url' => $this->url, 'icon' => $this->icon, 'group' => $this->group];
    }
}
