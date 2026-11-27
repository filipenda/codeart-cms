<?php

namespace Codeart\Core\Tables;

use Codeart\Core\Auth\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/** The endpoints every registered table shares: bulk actions (POST) and CSV export (GET). Both re-check permissions. */
final class TableController
{
    public function __construct(private readonly TableRegistry $registry, private readonly BulkActionRunner $runner, private readonly CsvExporter $exporter) {}

    public function bulk(Request $request, string $table): RedirectResponse
    {
        $definition = $this->registry->get($table) ?? abort(404);
        $user = $request->user(Admin::guardName());

        $ids = null;
        if ($request->input('scope') !== 'filter') {
            $raw = $request->input('ids');
            $ids = is_array($raw) ? array_values(array_unique(array_map(strval(...), array_filter($raw, is_scalar(...))))) : [];
        }
        $state = $definition->stateFrom($request->all());

        try {
            $result = $this->runner->run($table, $definition, (string) $request->input('action'), $state, $ids, $user);
            $toast = ['type' => $result->tone(), 'message' => $result->message()];
        } catch (AuthorizationException) {
            abort(403);
        } catch (BulkLimitException $e) {
            $toast = ['type' => 'danger', 'message' => $e->getMessage()];
        } catch (InvalidArgumentException) {
            $toast = ['type' => 'danger', 'message' => __('codeart::table.bulk_unknown')];
        }

        return back()->with('toast', $toast);
    }

    public function export(Request $request, string $table): Response|RedirectResponse
    {
        $definition = $this->registry->get($table) ?? abort(404);
        abort_if($definition->exportName() === null, 404);
        $user = $request->user(Admin::guardName());
        abort_unless($definition->canView($user), 403);

        try {
            return $this->exporter->response($table, $definition, $definition->stateFrom($request), $user);
        } catch (BulkLimitException $e) {
            return back()->with('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }
}
