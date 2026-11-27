<?php

namespace Codeart\Core\Tables;

use Codeart\Core\ActivityLog\ActivityLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * Runs a bulk action over the chosen rows, always in chunks (`chunkById`, default 500): neither the rows nor their IDs
 * are ever loaded all at once. The rows are either the ticked ids of one page (at most 100) or "everything matching the
 * filter", which is described by the filter and never by a list of ids.
 *
 * Policy (docs/DECISIONS.md TB5): up to `queue_limit` rows (default 1000) run inside the request. Above it the action is
 * queued when a real queue driver is configured; with `sync`/`null` (no worker, typical shared hosting) it still runs in
 * chunks inside the request up to `hard_cap` (default 10000) and is refused above that with a "narrow the filter" message.
 */
final class BulkActionRunner
{
    /**
     * @param  list<string>|null  $ids  ticked rows of the page; null = every row matching `$state`
     *
     * @throws AuthorizationException when the user may not see the table or run the action
     * @throws BulkLimitException when too many rows match and the work cannot be queued
     * @throws InvalidArgumentException for an unknown action or an oversized id list
     */
    public function run(string $tableKey, DataTable $table, string $actionKey, TableState $state, ?array $ids, ?Authenticatable $user): BulkResult
    {
        $action = $this->authorize($table, $actionKey, $user);

        if ($ids !== null && count($ids) > TableState::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Too many ids');
        }
        if ($ids === []) {
            return new BulkResult;
        }

        $matched = $this->query($table, $state, $ids)->toBase()->count();
        $queueLimit = (int) config('codeart.tables.bulk.queue_limit', 1000);

        if ($matched > $queueLimit && $action->queueable && $this->canQueue()) {
            RunBulkActionJob::dispatch($tableKey, $actionKey, $state->toQuery($table), $ids, $user?->getAuthIdentifier());
            $this->log($table, $action, $user, $matched, 'queued');

            return BulkResult::queued($matched);
        }
        $cap = (int) config('codeart.tables.bulk.hard_cap', 10000);
        if ($matched > $cap) {
            throw new BulkLimitException($matched, $cap);
        }

        $result = $this->process($table, $action, $this->query($table, $state, $ids));
        $this->log($table, $action, $user, $matched, 'done');

        return $result;
    }

    /**
     * The chunked loop itself (also used by the queued job). Counts what each row's handler returned.
     *
     * @param  Builder<*>  $query
     */
    public function process(DataTable $table, BulkAction $action, Builder $query): BulkResult
    {
        $result = new BulkResult;
        $key = $query->getModel()->getKeyName();

        $query->reorder()->with($table->eagerLoads())->chunkById(
            (int) config('codeart.tables.bulk.chunk', 500),
            function (Collection $rows) use ($action, $result): void {
                foreach ($rows as $row) {
                    try {
                        $outcome = ($action->handle)($row);
                    } catch (Throwable $e) {
                        report($e);
                        $outcome = false;
                    }
                    match ($outcome) {
                        null => $result->skipped++,
                        false => $result->failed++,
                        default => $result->done++,
                    };
                }
            },
            $query->getModel()->qualifyColumn($key),
            $key,
        );

        return $result;
    }

    /**
     * The rows an action applies to.
     *
     * @param  list<string>|null  $ids
     * @return Builder<*>
     */
    public function query(DataTable $table, TableState $state, ?array $ids): Builder
    {
        $query = $table->filteredQuery($ids === null ? $state : new TableState);

        return $ids === null ? $query : $query->whereKey($ids);
    }

    /** @throws AuthorizationException */
    public function authorize(DataTable $table, string $actionKey, ?Authenticatable $user): BulkAction
    {
        $action = $table->bulkAction($actionKey) ?? throw new InvalidArgumentException("Unknown action {$actionKey}");
        if (! $table->canView($user) || ! $action->allowedFor($user)) {
            throw new AuthorizationException;
        }

        return $action;
    }

    /** A real queue driver (database, redis, ...) can take the work; `sync` and `null` cannot. */
    public function canQueue(): bool
    {
        $default = (string) config('queue.default', 'sync');

        return ! in_array((string) config("queue.connections.{$default}.driver", $default), ['sync', 'null'], true);
    }

    private function log(DataTable $table, BulkAction $action, ?Authenticatable $user, int $matched, string $status): void
    {
        app(ActivityLog::class)->record('table.bulk', "Bulk action {$action->key} on {$table->id}", null, $user instanceof Model ? $user : null, ['table' => $table->id, 'action' => $action->key, 'rows' => $matched, 'status' => $status], 'tables');
    }
}
