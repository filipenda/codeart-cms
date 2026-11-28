<?php

namespace Codeart\Core\Tables;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** One page of a table, ready to render with `<x-codeart::table :result="$result" />` (or to redirect when the page was past the end). */
final class TableResult
{
    /** @param Collection<int, Model> $rows */
    public function __construct(
        public readonly DataTable $table,
        public readonly TableState $state,
        public readonly Collection $rows,
        public readonly int $total,
        public readonly string $baseUrl,
        public readonly ?Authenticatable $user = null,
        public readonly ?string $redirectTo = null,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->state->perPage));
    }

    public function from(): int
    {
        return $this->rows->isEmpty() ? 0 : ($this->state->page - 1) * $this->state->perPage + 1;
    }

    public function to(): int
    {
        return $this->rows->isEmpty() ? 0 : $this->from() + $this->rows->count() - 1;
    }

    /** @param array<string, mixed> $override */
    public function url(array $override = []): string
    {
        $query = $this->state->toQuery($this->table, $override);

        return $this->baseUrl.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * The state as hidden form fields (`name` => value, nested keys as `filter[status]`), so a form on the page keeps
     * the search, filters and sort. Page and any keys in `$except` are left out.
     *
     * @param  list<string>  $except  top-level keys to drop (`q`, `sort`, `dir`, `filter`, `per_page`, `page`)
     * @return array<string, string>
     */
    public function hiddenFields(array $except = ['page']): array
    {
        $fields = [];
        foreach (Arr::except($this->state->toQuery($this->table), $except) as $key => $value) {
            if (is_array($value)) {
                foreach (Arr::dot($value) as $path => $leaf) {
                    $parts = explode('.', (string) $path);
                    $fields[$key.'['.implode('][', $parts).']'] = (string) $leaf;
                }
            } else {
                $fields[$key] = (string) $value;
            }
        }

        return $fields;
    }
}
