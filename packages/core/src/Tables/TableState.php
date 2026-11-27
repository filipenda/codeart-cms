<?php

namespace Codeart\Core\Tables;

/**
 * Everything the visitor chose, parsed from the query string (`q`, `sort`, `dir`, `filter[...]`, `per_page`, `page`).
 * Anything invalid is dropped or clamped, never an error: a hand-edited URL still gives a sensible page.
 */
final class TableState
{
    public const int MAX_PER_PAGE = 100;

    public const int MAX_PAGE = 100_000_000;

    public const int MAX_SEARCH = 200;

    /** @param array<string, mixed> $filters key => normalised value */
    public function __construct(
        public readonly string $q = '',
        public readonly string $sort = '',
        public readonly string $dir = 'asc',
        public readonly array $filters = [],
        public readonly int $perPage = 25,
        public readonly int $page = 1,
    ) {}

    /** @param array<string, mixed> $input a query string or a request's input */
    public static function fromArray(array $input, DataTable $table): self
    {
        $q = is_string($input['q'] ?? null) ? trim(mb_substr($input['q'], 0, self::MAX_SEARCH)) : '';

        [$defaultSort, $defaultDir] = $table->defaultSortOrder();
        $sort = is_string($input['sort'] ?? null) && $table->sortableColumn($input['sort']) !== null ? $input['sort'] : $defaultSort;
        $dir = is_string($input['dir'] ?? null) && in_array(strtolower($input['dir']), ['asc', 'desc'], true)
            ? strtolower($input['dir'])
            : ((is_string($input['sort'] ?? null) && $sort === $input['sort'] && $sort !== $defaultSort) ? 'asc' : $defaultDir);

        $filters = [];
        if (is_array($input['filter'] ?? null)) {
            foreach ($input['filter'] as $key => $raw) {
                $filter = is_string($key) ? $table->filterFor($key) : null;
                if ($filter !== null && ($value = $filter->normalize($raw)) !== null) {
                    $filters[$key] = $value;
                }
            }
        }

        return new self($q, $sort, $dir, $filters, self::clamp($input['per_page'] ?? null, $table->defaultPerPage(), 1, self::MAX_PER_PAGE), self::clamp($input['page'] ?? null, 1, 1, self::MAX_PAGE));
    }

    public function withPage(int $page): self
    {
        return new self($this->q, $this->sort, $this->dir, $this->filters, $this->perPage, max(1, $page));
    }

    public function hasCriteria(): bool
    {
        return $this->q !== '' || $this->filters !== [];
    }

    /**
     * Query-string array for this state with the given overrides (`['page' => 3]`, `['sort' => 'title', 'dir' => 'desc']`);
     * values equal to the defaults are left out so URLs stay short.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public function toQuery(DataTable $table, array $override = []): array
    {
        $q = $override['q'] ?? $this->q;
        $sort = $override['sort'] ?? $this->sort;
        $dir = $override['dir'] ?? $this->dir;
        $filters = array_key_exists('filter', $override) ? $override['filter'] : $this->filters;
        $perPage = $override['per_page'] ?? $this->perPage;
        $page = $override['page'] ?? $this->page;
        [$defaultSort, $defaultDir] = $table->defaultSortOrder();

        $query = [];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($sort !== $defaultSort || $dir !== $defaultDir) {
            $query['sort'] = $sort;
            $query['dir'] = $dir;
        }
        foreach ($filters as $key => $value) {
            $query['filter'][$key] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }
        if ($perPage !== $table->defaultPerPage()) {
            $query['per_page'] = $perPage;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    private static function clamp(mixed $raw, int $default, int $min, int $max): int
    {
        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && preg_match('/^\s*-?\d{1,18}\s*$/', $raw) === 1) {
            $value = (int) $raw;
        } else {
            return $default;
        }

        return $value < $min ? $default : min($max, $value);
    }
}
