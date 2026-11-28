<?php

namespace Codeart\Core\Tables;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** A bulk action too big for one request, run by a queue worker. Rebuilds the table from the registry key, re-checks permission. */
final class RunBulkActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $query  the state as a query string array
     * @param  list<string>|null  $ids
     */
    public function __construct(public string $tableKey, public string $actionKey, public array $query, public ?array $ids, public int|string|null $userId) {}

    public function handle(TableRegistry $registry, BulkActionRunner $runner): void
    {
        $table = $registry->get($this->tableKey);
        $found = $this->userId === null ? null : Admin::userModel()::query()->find($this->userId);
        $user = $found instanceof Authenticatable ? $found : null;
        if ($table === null) {
            return;
        }
        $action = $runner->authorize($table, $this->actionKey, $user);
        $state = $table->stateFrom($this->query);
        $result = $runner->process($table, $action, $runner->query($table, $state, $this->ids));

        app(ActivityLog::class)->record('table.bulk', "Bulk action {$action->key} on {$table->id} finished", null, $user instanceof Model ? $user : null,
            ['table' => $table->id, 'action' => $action->key, 'done' => $result->done, 'failed' => $result->failed, 'skipped' => $result->skipped], 'tables');
    }
}
