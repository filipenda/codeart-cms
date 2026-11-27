<?php

namespace Codeart\Core\Tables;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
}
