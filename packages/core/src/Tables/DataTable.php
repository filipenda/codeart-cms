<?php

namespace Codeart\Core\Tables;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A server-side table: base query, columns, filters, eager loads, actions and export. All the state is in the query
 * string (`TableState`), a request loads exactly one page (at most 100 rows) and the total is counted exactly.
 *
 * Column expressions are written by the developer (never taken from the request): sorting, searching and filtering
 * only ever use the declared ones. The base query must return one row per model (no GROUP BY) so counts stay right.
 *
 *     DataTable::make('pages', Page::query())
 *         ->columns(Column::titleSubtitle('title', 'Title'), Column::status('status', 'Status', [...]), Column::date('created_at', 'Created'))
 *         ->filters(Filter::status('status', 'Status', [...]))
 *         ->rowActions(RowAction::edit(fn (Page $p) => route('pages.edit', $p), 'pages.edit'))
 *         ->defaultSort('created_at', 'desc')->export('pages');
 */
final class DataTable
{
    /** @var list<Column> */
    private array $columns = [];

    /** @var array<string, Filter> declared filters */
    private array $filters = [];

    /** @var array<string, Filter> implicit filters of the columns (built lazily) */
    private ?array $columnFilters = null;

    /** @var list<string> */
    private array $with = [];

    /** @var list<RowAction> */
    private array $rowActions = [];

    /** @var array<string, BulkAction> */
    private array $bulkActions = [];

    private string $sortKey = '';

    private string $sortDir = 'desc';

    private int $perPage;

    private ?string $permission = null;

    private ?string $exportName = null;

    /** @param Closure(): Builder<*> $query a fresh base query each call */
    private function __construct(public readonly string $id, private readonly Closure $query)
    {
        $this->perPage = max(1, min(TableState::MAX_PER_PAGE, (int) config('codeart.tables.default_per_page', 25)));
    }

    /** @param Builder<*>|Closure(): Builder<*> $query */
    public static function make(string $id, Builder|Closure $query): self
    {
        return new self($id, $query instanceof Closure ? $query : fn (): Builder => clone $query);
    }

    public function columns(Column ...$columns): self
    {
        foreach ($columns as $column) {
            $this->columns[] = $column;
        }
        $this->columnFilters = null;

        return $this;
    }

    public function filters(Filter ...$filters): self
    {
        foreach ($filters as $filter) {
            $this->filters[$filter->key] = $filter;
        }

        return $this;
    }

    /** Relations to eager load for the rows shown (and for export and bulk chunks): keeps the query count constant. */
    public function with(string ...$relations): self
    {
        $this->with = array_values(array_unique([...$this->with, ...$relations]));

        return $this;
    }

    public function rowActions(RowAction ...$actions): self
    {
        $this->rowActions = array_values($actions);

        return $this;
    }

    public function bulkActions(BulkAction ...$actions): self
    {
        foreach ($actions as $action) {
            $this->bulkActions[$action->key] = $action;
        }

        return $this;
    }

    public function defaultSort(string $key, string $dir = 'asc'): self
    {
        $this->sortKey = $key;
        $this->sortDir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function perPage(int $default): self
    {
        $this->perPage = max(1, min(TableState::MAX_PER_PAGE, $default));

        return $this;
    }

    /** Ability needed to see the list, export it and run its bulk actions (a Gate ability or a permission). */
    public function permission(?string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /** Enable CSV export; `$filename` is the base name of the file (without extension). */
    public function export(?string $filename = null): self
    {
        $this->exportName = $filename ?? $this->id;

        return $this;
    }

    // ---------------------------------------------------------------------------------------------------- reading

    /** @return list<Column> */
    public function allColumns(): array
    {
        return $this->columns;
    }

    public function column(string $key): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    public function sortableColumn(string $key): ?Column
    {
        $column = $this->column($key);

        return $column !== null && $column->sortable ? $column : null;
    }

    /** @return array<string, Filter> declared filters, in order */
    public function declaredFilters(): array
    {
        return $this->filters;
    }

    /** The declared filter, else the implicit one of the column with that key. */
    public function filterFor(string $key): ?Filter
    {
        if (isset($this->filters[$key])) {
            return $this->filters[$key];
        }

        return $this->columnFilters()[$key] ?? null;
    }

    /** @return array<string, Filter> implicit filters of the filterable columns that have no declared filter */
    public function columnFilters(): array
    {
        if ($this->columnFilters === null) {
            $this->columnFilters = [];
            foreach ($this->columns as $column) {
                if ($column->filterable && $column->type !== 'actions') {
                    $this->columnFilters[$column->key] = $this->filterOf($column);
                }
            }
        }

        return $this->columnFilters;
    }

    /** @return list<RowAction> */
    public function allRowActions(): array
    {
        return $this->rowActions;
    }

    /** @return array<string, BulkAction> */
    public function allBulkActions(): array
    {
        return $this->bulkActions;
    }

    public function bulkAction(string $key): ?BulkAction
    {
        return $this->bulkActions[$key] ?? null;
    }

    public function exportName(): ?string
    {
        return $this->exportName;
    }

    public function defaultPerPage(): int
    {
        return $this->perPage;
    }

    /** @return array{0: string, 1: string} sort key and direction when the URL names none (first sortable column if undeclared) */
    public function defaultSortOrder(): array
    {
        if ($this->sortKey !== '' && $this->sortableColumn($this->sortKey) !== null) {
            return [$this->sortKey, $this->sortDir];
        }
        foreach ($this->columns as $column) {
            if ($column->sortable) {
                return [$column->key, 'asc'];
            }
        }

        return ['', 'asc'];
    }

    public function canView(?Authenticatable $user): bool
    {
        return $this->permission === null || ($user !== null && Gate::forUser($user)->allows($this->permission));
    }

    public function keyName(): string
    {
        return ($this->query)()->getModel()->getKeyName();
    }

    // ---------------------------------------------------------------------------------------------------- queries

    /** A column expression as SQL: a plain `column` or `table.column` is quoted (and qualified), anything else is the developer's raw SQL. */
    public function sql(string $expression): ExpressionContract
    {
        $query = ($this->query)();
        $grammar = $query->getQuery()->getGrammar();

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return new Expression($grammar->wrap($query->getModel()->qualifyColumn($expression)));
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return new Expression($grammar->wrap($expression));
        }

        return new Expression($expression);
    }

    /** @param Request|array<string, mixed> $input */
    public function stateFrom(Request|array $input): TableState
    {
        return TableState::fromArray($input instanceof Request ? $input->query() : $input, $this);
    }

    /**
     * Base query narrowed by the search and the filters; no order, no eager loads.
     *
     * @return Builder<*>
     */
    public function filteredQuery(TableState $state): Builder
    {
        $query = ($this->query)();
        $this->search($query, $state->q);
        foreach ($state->filters as $key => $value) {
            $this->filterFor($key)?->applyTo($this, $query, $value);
        }

        return $query;
    }

    /**
     * The same rows in the table's order (the sort column, then the key as a stable tie-breaker).
     *
     * @return Builder<*>
     */
    public function orderedQuery(TableState $state): Builder
    {
        return $this->order($this->filteredQuery($state), $state);
    }

    /**
     * Add the declared order of `$state` (sort column, then the key) to `$query`.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function order(Builder $query, TableState $state): Builder
    {
        $column = $this->sortableColumn($state->sort);
        $dir = $state->dir === 'desc' ? 'desc' : 'asc';
        if ($column !== null && ! $this->isKey($column, $query)) {
            $query->orderByRaw($this->sql($column->expression)->getValue($query->getQuery()->getGrammar()).' '.$dir);
        }
        $query->orderBy($query->getModel()->qualifyColumn($query->getModel()->getKeyName()), $column !== null ? $dir : 'desc');

        return $query;
    }

    /**
     * True when ordering by this column is already ordering by the primary key.
     *
     * @param  Builder<*>  $query
     */
    public function isKey(Column $column, Builder $query): bool
    {
        return $column->expression === $query->getModel()->getKeyName() || $column->expression === $query->getModel()->qualifyColumn($query->getModel()->getKeyName());
    }

    /** Load one page for the request; `redirectTo` is set when the page is past the last one. */
    public function resolve(Request $request, ?Authenticatable $user = null): TableResult
    {
        $state = $this->stateFrom($request);
        $baseUrl = $request->url();
        $total = $this->filteredQuery($state)->toBase()->count();
        $last = max(1, (int) ceil($total / $state->perPage));

        if ($state->page > $last) {
            $lastState = $state->withPage($last);

            return new TableResult($this, $lastState, new EloquentCollection, $total, $baseUrl, $user, (new TableResult($this, $lastState, new EloquentCollection, $total, $baseUrl))->url());
        }

        $rows = $this->orderedQuery($state)->with($this->with)
            ->offset(($state->page - 1) * $state->perPage)->limit($state->perPage)->get();

        return new TableResult($this, $state, $rows, $total, $baseUrl, $user);
    }

    /**
     * Walk the rows of `$state` in the table's order, `$size` at a time (with the eager loads); never loads them all.
     * Ordering by the key uses keyset paging (constant cost); any other sort uses offsets (later chunks cost more).
     * Stops after `$limit` rows when given. `$each` gets the chunk (an Eloquent collection) and returns false to stop.
     *
     * @param  Closure(EloquentCollection<int, Model>): mixed  $each
     */
    public function eachOrdered(TableState $state, int $size, Closure $each, ?int $limit = null): void
    {
        $column = $this->sortableColumn($state->sort);
        $dir = $state->dir === 'desc' ? 'desc' : 'asc';
        $sample = ($this->query)();
        $byKey = $column === null || $this->isKey($column, $sample);
        $key = $sample->getModel()->getKeyName();
        $qualified = $sample->getModel()->qualifyColumn($key);
        $seen = 0;
        $after = null;
        $offset = 0;

        while (true) {
            $take = $limit === null ? $size : min($size, $limit - $seen);
            if ($take <= 0) {
                return;
            }
            $query = $byKey ? $this->filteredQuery($state)->orderBy($qualified, $column === null ? 'desc' : $dir) : $this->orderedQuery($state);
            if ($byKey && $after !== null) {
                $query->where($qualified, ($column === null || $dir === 'desc') ? '<' : '>', $after);
            }
            if (! $byKey) {
                $query->offset($offset);
            }
            $rows = $query->with($this->with)->limit($take)->get();
            if ($rows->isEmpty() || ($each($rows) === false)) {
                return;
            }
            $seen += $rows->count();
            $offset += $rows->count();
            $after = $rows->last()->getAttribute($key);
            if ($rows->count() < $take) {
                return;
            }
        }
    }

    /** @return list<string> */
    public function eagerLoads(): array
    {
        return $this->with;
    }

    // ---------------------------------------------------------------------------------------------------- internals

    /**
     * Words are AND-ed; each word must be contained (case-insensitive by the column collation) in at least one searchable column.
     *
     * @param  Builder<*>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $columns = array_values(array_filter($this->columns, fn (Column $c): bool => $c->searchable && $c->type !== 'actions'));
        $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($columns === [] || $words === []) {
            return;
        }
        $grammar = $query->getQuery()->getGrammar();
        foreach ($words as $word) {
            $like = '%'.Filter::escapeLike($word).'%';
            $query->where(function (Builder $group) use ($columns, $like, $grammar): void {
                foreach ($columns as $column) {
                    $group->orWhereRaw($this->sql($column->expression)->getValue($grammar)." LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }
    }

    private function filterOf(Column $column): Filter
    {
        return match ($column->type) {
            'number' => Filter::numberRange($column->key, $column->label, $column->expression),
            'date' => Filter::dateRange($column->key, $column->label, $column->expression),
            'boolean' => Filter::boolean($column->key, $column->label, $column->expression),
            'status', 'badge' => Filter::select($column->key, $column->label, array_map(fn (array $o): string => $o[0], $column->options), $column->expression),
            default => Filter::text($column->key, $column->label, $column->expression),
        };
    }
}
