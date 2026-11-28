<?php

namespace Codeart\Core\Tables;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Confirmation\ConfirmationTokens;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints every registered table shares: bulk actions (POST) and CSV export (GET). Both re-check permissions.
 * A destructive bulk action runs only with a confirmation token issued by this same endpoint in a previous step (TB11):
 * without one the response is the confirmation page (or, for the dialog, JSON with the texts and a fresh token).
 */
final class TableController
{
    public function __construct(private readonly TableRegistry $registry, private readonly BulkActionRunner $runner, private readonly CsvExporter $exporter, private readonly ConfirmationTokens $tokens) {}

    public function bulk(Request $request, string $table): RedirectResponse|View|JsonResponse
    {
        $definition = $this->registry->get($table) ?? abort(404);
        $user = $request->user(Admin::guardName());

        $ids = null;
        if ($request->input('scope') !== 'filter') {
            $raw = $request->input('ids');
            $ids = is_array($raw) ? array_values(array_unique(array_map(strval(...), array_filter($raw, is_scalar(...))))) : [];
        }
        $state = $definition->stateFrom($request->all());
        $actionKey = (string) $request->input('action');

        try {
            $action = $this->runner->authorize($definition, $actionKey, $user);
            if ($action->destructive && $ids !== [] && ($ids === null || count($ids) <= TableState::MAX_PER_PAGE)) {
                $scope = "bulk:{$table}:{$action->key}";
                $payload = $this->payload($definition, $state, $ids);
                if (! $this->tokens->consume($request->input('confirmation'), $user, $scope, $payload)) {
                    return $this->confirmation($request, $table, $definition, $action, $state, $ids, $user, $scope, $payload);
                }
            }

            $result = $this->runner->run($table, $definition, $actionKey, $state, $ids, $user);
            $toast = ['type' => $result->tone(), 'message' => $result->message()];
        } catch (AuthorizationException) {
            abort(403);
        } catch (BulkLimitException $e) {
            $toast = ['type' => 'danger', 'message' => $e->getMessage()];
        } catch (InvalidArgumentException) {
            $toast = ['type' => 'danger', 'message' => __('codeart::table.bulk_unknown')];
        }

        return $this->back($request)->with('toast', $toast);
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

    /**
     * What the token is bound to: the exact ticked ids, or the filter (never ids) for "all matching".
     *
     * @param  list<string>|null  $ids
     * @return array<string, mixed>
     */
    private function payload(DataTable $definition, TableState $state, ?array $ids): array
    {
        if ($ids !== null) {
            sort($ids, SORT_STRING);

            return ['ids' => $ids];
        }

        return ['filter' => Arr::except($state->toQuery($definition), ['page', 'per_page', 'sort', 'dir'])];
    }

    /**
     * @param  list<string>|null  $ids
     * @param  array<string, mixed>  $payload
     */
    private function confirmation(Request $request, string $table, DataTable $definition, BulkAction $action, TableState $state, ?array $ids, ?Authenticatable $user, string $scope, array $payload): View|JsonResponse
    {
        $count = $ids !== null ? count($ids) : $this->runner->query($definition, $state, null)->toBase()->count();
        $filter = $ids === null ? $this->describeFilter($definition, $state) : null;
        $text = $action->confirm ?? __('codeart::table.bulk_confirm');
        $token = $this->tokens->issue($user, $scope, $payload);

        if ($request->expectsJson()) {
            return response()->json(['token' => $token, 'title' => $action->label, 'text' => $text, 'count' => $count, 'filter' => $filter, 'ttl' => ConfirmationTokens::TTL]);
        }

        $fields = [];
        foreach (Arr::dot($request->except(['_token', 'confirmation'])) as $name => $value) {
            $parts = explode('.', (string) $name);
            $fields[array_shift($parts).($parts === [] ? '' : '['.implode('][', $parts).']')] = (string) $value;
        }

        return view('codeart::admin.confirm', [
            'title' => $action->label,
            'text' => $text,
            'count' => $count,
            'filter' => $filter,
            'target' => route('codeart.tables.bulk', ['table' => $table]),
            'method' => 'POST',
            'fields' => $fields,
            'token' => $token,
            'back' => $this->returnUrl($request),
            'expired' => $request->filled('confirmation'),
        ]);
    }

    /** The active search and filters in words, for the confirmation of "all matching". */
    private function describeFilter(DataTable $definition, TableState $state): string
    {
        $parts = [];
        if ($state->q !== '') {
            $parts[] = __('codeart::table.search').': '.$state->q;
        }
        foreach ($state->filters as $key => $value) {
            $label = $definition->filterFor($key)->label ?? $key;
            $parts[] = $label.': '.(is_array($value) ? implode(' – ', array_filter(array_map(strval(...), array_map(fn ($v) => is_scalar($v) ? $v : '', $value)))) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value));
        }

        return $parts === [] ? __('codeart::table.confirm_no_filter') : implode('; ', $parts);
    }

    /** Where to go after the action: the page the bulk bar was on (a same-site path), else the previous page. */
    private function returnUrl(Request $request): string
    {
        $return = (string) $request->input('return', '');
        if ($return !== '' && str_starts_with($return, '/') && ! str_starts_with($return, '//') && ! str_contains($return, '\\')) {
            return url($return);
        }

        return url()->previous();
    }

    private function back(Request $request): RedirectResponse
    {
        return $request->filled('return') ? redirect()->to($this->returnUrl($request)) : back();
    }
}
