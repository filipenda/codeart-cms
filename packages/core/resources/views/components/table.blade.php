@props(['result', 'label' => null])
@php
    use Codeart\Core\Auth\Admin;
    use Codeart\Core\Tables\TableRegistry;
    use Illuminate\Support\Facades\Route;

    // Server-rendered data table (docs/components.md). Everything is plain HTML: the toolbar is a GET form, sorting and
    // paging are links, the bulk bar a POST form. Alpine only adds debounced search, auto-submit and the selection counter.
    $table = $result->table;
    $state = $result->state;
    $user = $result->user ?? Admin::currentUser();
    $id = 'tbl-'.$table->id;
    $columns = array_values(array_filter($table->allColumns(), fn ($c) => $c->type !== 'actions'));
    $registered = app(TableRegistry::class)->has($table->id);
    $rowActions = array_values(array_filter($table->allRowActions(), fn ($a) => $a->allowedFor($user)));
    $bulkActions = $registered && $table->canView($user) ? array_values(array_filter($table->allBulkActions(), fn ($a) => $a->allowedFor($user))) : [];
    $canExport = $registered && $table->exportName() !== null && $table->canView($user) && Route::has('codeart.tables.export');
    $canBulk = $bulkActions !== [] && Route::has('codeart.tables.bulk');
    $declared = $table->declaredFilters();
    $panel = array_filter($table->columnFilters(), fn ($f, $key) => ! isset($declared[$key]) && ($table->column($key)?->type ?? '') !== 'thumbnail' && ($table->column($key)?->type ?? '') !== 'language-chips', ARRAY_FILTER_USE_BOTH);
    $panelOpen = array_intersect_key($state->filters, $panel) !== [];
    $label ??= $table->id;
    $perPage = [10 => '10', 25 => '25', 50 => '50', 100 => '100'];
    $perPage[$state->perPage] ??= (string) $state->perPage;
    ksort($perPage);
    $first = $canBulk ? 'start-11' : 'start-0';
    $empty = $result->total === 0 && ! $state->hasCriteria();
    $field = 'h-11 w-full rounded-control border border-border bg-surface px-3 text-sm text-text placeholder:text-text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';
@endphp
<div id="{{ $id }}" class="space-y-4" data-table="{{ $table->id }}" x-data="codeartTable({ total: {{ $result->total }}, rows: {{ $result->rows->count() }} })" x-init="init()">
    @unless ($empty)
        {{-- Toolbar: a GET form, so every list is linkable and works without JavaScript. Page resets to 1 on a new search. --}}
        <form method="GET" action="{{ $result->baseUrl }}" role="search" aria-label="{{ $label }}" x-ref="form" class="space-y-3" data-toolbar x-data="{ filtersOpen: {{ $panelOpen ? 'true' : 'false' }} }">
            @foreach ($result->hiddenFields(['q', 'filter', 'page']) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-56 flex-1 sm:max-w-sm">
                    <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-text-muted"><x-codeart::icon name="search" class="size-5" /></span>
                    <input type="search" name="q" value="{{ $state->q }}" maxlength="200" placeholder="{{ __('codeart::table.search_placeholder') }}" aria-label="{{ __('codeart::table.search') }}" autocomplete="off"
                        class="{{ $field }} ps-10" x-on:input.debounce.400ms="submit()" data-search>
                </div>
                @foreach ($declared as $key => $filter)
                    <div class="min-w-40"><x-codeart::table.filter :filter="$filter" :value="$state->filters[$key] ?? null" /></div>
                @endforeach
                <x-codeart::button type="submit" variant="secondary" size="md" :full="false" icon-left="search">{{ __('codeart::table.apply') }}</x-codeart::button>
                @if ($panel !== [])
                    <button type="button" x-on:click="filtersOpen = ! filtersOpen" x-bind:aria-expanded="filtersOpen.toString()" aria-controls="{{ $id }}-more" aria-expanded="{{ $panelOpen ? 'true' : 'false' }}" data-more-filters-toggle
                        class="inline-flex h-11 items-center gap-2 rounded-control border border-border bg-surface px-3 text-sm font-medium text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
                        <x-codeart::icon name="sliders-horizontal" class="size-4" /> {{ __('codeart::table.more_filters') }}
                    </button>
                @endif
                @if ($state->hasCriteria())
                    <x-codeart::button :href="$result->url(['q' => '', 'filter' => [], 'page' => 1])" variant="ghost" size="md" :full="false" icon-left="rotate-ccw" data-reset>{{ __('codeart::table.reset') }}</x-codeart::button>
                @endif
                @if ($canExport)
                    <x-codeart::button :href="route('codeart.tables.export', ['table' => $table->id] + $state->toQuery($table, ['page' => 1]))" variant="ghost" size="md" :full="false" icon-left="download" class="ms-auto" data-export>{{ __('codeart::table.export') }}</x-codeart::button>
                @endif
            </div>
            @if ($panel !== [])
                {{-- Without JavaScript the panel is simply shown; Alpine hides it until the toggle button opens it. --}}
                <div id="{{ $id }}-more" class="rounded-card border border-border bg-surface" x-show="filtersOpen" x-cloak data-more-filters @if ($panelOpen) data-open="1" @endif>
                    <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($panel as $key => $filter)
                            <div class="space-y-1.5">
                                <span class="block text-xs font-medium text-text-muted" aria-hidden="true">{{ $filter->label }}</span>
                                <x-codeart::table.filter :filter="$filter" :value="$state->filters[$key] ?? null" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </form>
    @endunless

    @if ($canBulk && ! $empty)
        {{-- Bulk bar. Checkboxes in the rows belong to this form through their `form` attribute. "All results" sends the FILTER, never ids. --}}
        <form method="POST" action="{{ route('codeart.tables.bulk', ['table' => $table->id]) }}" id="{{ $id }}-bulk" x-ref="bulk" x-show="count > 0" x-on:submit="confirmBulk($event)" data-bulk-bar
            class="flex flex-wrap items-center gap-3 rounded-card border border-border bg-primary-soft px-4 py-3">
            @csrf
            <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
            <input type="hidden" name="confirmation" value="" data-bulk-token disabled>
            @foreach ($result->hiddenFields(['page', 'per_page']) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <span class="text-sm font-semibold text-text" aria-live="polite" data-selected-label="{{ __('codeart::table.selected', ['count' => ':count']) }}" x-text="selectedLabel()">{{ __('codeart::table.bulk') }}</span>
            @if ($result->total > $result->rows->count())
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-text" x-show="pageAll || allMatching">
                    <input type="checkbox" name="scope" value="filter" class="size-5 cursor-pointer rounded-sm border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" x-model="allMatching" data-all-matching>
                    {{ __('codeart::table.select_all_matching', ['count' => number_format($result->total, 0, ',', '.')]) }}
                </label>
            @endif
            <div class="min-w-48">
                <x-codeart::table.select name="action" :label="__('codeart::table.bulk')" :placeholder="__('codeart::table.bulk_choose')" required>
                    @foreach ($bulkActions as $action)
                        <option value="{{ $action->key }}" data-confirm="{{ $action->confirm ?? __('codeart::table.bulk_confirm') }}" @if ($action->destructive) data-destructive="1" @endif>{{ $action->label }}</option>
                    @endforeach
                </x-codeart::table.select>
            </div>
            <x-codeart::button type="submit" size="md" :full="false">{{ __('codeart::table.bulk_run') }}</x-codeart::button>
        </form>
        {{-- Confirmation dialog for bulk runs. A destructive action gets its token from the server first (the same endpoint answers JSON). --}}
        <x-codeart::modal :id="$id.'-confirm'" size="sm" role="alertdialog" :title="__('codeart::table.confirm_title')" data-bulk-dialog>
            <p data-bulk-text></p>
            <p class="mt-2 text-text-muted" data-bulk-count-line hidden>{{ __('codeart::table.confirm_rows') }}: <span data-bulk-count></span></p>
            <p class="mt-1 text-text-muted" data-bulk-filter-line hidden>{{ __('codeart::table.confirm_filter') }}: <span data-bulk-filter></span></p>
            <x-slot:footer>
                <x-codeart::button type="button" variant="secondary" size="md" :full="false" data-modal-close>{{ __('codeart::ui.c.cancel') }}</x-codeart::button>
                <x-codeart::button type="button" variant="danger" size="md" :full="false" data-bulk-confirm>{{ __('codeart::table.confirm_run') }}</x-codeart::button>
            </x-slot:footer>
        </x-codeart::modal>
    @endif

    @if ($empty)
        <x-codeart::empty-state :title="__('codeart::table.empty_title')" :text="__('codeart::table.empty_text')" icon="inbox">{{ $empty ?? '' }}</x-codeart::empty-state>
    @elseif ($result->rows->isEmpty())
        <x-codeart::empty-state :title="__('codeart::table.no_results_title')" :text="__('codeart::table.no_results_text')" icon="search" data-no-results>
            <x-codeart::button :href="$result->url(['q' => '', 'filter' => [], 'page' => 1])" variant="secondary" size="md" :full="false" icon-left="rotate-ccw">{{ __('codeart::table.reset') }}</x-codeart::button>
        </x-codeart::empty-state>
    @else
        {{-- Mobile: the table scrolls sideways inside its card and the first data column stays in view. --}}
        <div class="overflow-x-auto rounded-card border border-border bg-surface" data-scroll>
            <table class="w-full border-collapse text-start text-sm">
                <caption class="sr-only">{{ $label }}</caption>
                <thead class="bg-surface-alt text-xs uppercase tracking-wide text-text-muted">
                    <tr>
                        @if ($canBulk)
                            <th scope="col" class="sticky start-0 z-10 w-11 bg-surface-alt px-3 py-3">
                                <input type="checkbox" class="size-5 cursor-pointer rounded-sm border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" aria-label="{{ __('codeart::table.select_all_page') }}" x-on:change="toggleAll($event)" data-select-all>
                            </th>
                        @endif
                        @foreach ($columns as $column)
                            @php
                                $sorted = $state->sort === $column->key;
                                $next = $sorted && $state->dir === 'asc' ? 'desc' : 'asc';
                                $sticky = $loop->first ? 'sticky '.$first.' z-10 bg-surface-alt' : '';
                            @endphp
                            <th scope="col" @if ($column->sortable) aria-sort="{{ $sorted ? ($state->dir === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif
                                class="whitespace-nowrap px-4 py-3 font-semibold {{ $sticky }} {{ $column->type === 'number' ? 'text-end' : '' }}" data-column="{{ $column->key }}">
                                @if ($column->sortable)
                                    <a href="{{ $result->url(['sort' => $column->key, 'dir' => $next, 'page' => 1]) }}" title="{{ __('codeart::table.sort_by', ['column' => $column->label]) }}"
                                        class="inline-flex items-center gap-1.5 rounded-sm uppercase hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus {{ $sorted ? 'text-text' : '' }}">
                                        {{ $column->label }}
                                        <x-codeart::icon :name="$sorted ? ($state->dir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevrons-up-down'" class="size-4" data-sort-icon />
                                        @if ($sorted)<span class="sr-only">({{ __($state->dir === 'asc' ? 'codeart::table.sorted_ascending' : 'codeart::table.sorted_descending') }})</span>@endif
                                    </a>
                                @else
                                    {{ $column->label }}
                                @endif
                            </th>
                        @endforeach
                        @if ($rowActions !== [])
                            <th scope="col" class="px-4 py-3 text-end font-semibold"><span class="sr-only">{{ __('codeart::table.actions') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result->rows as $row)
                        <tr class="group border-t border-border hover:bg-surface-alt" data-row="{{ $row->getKey() }}">
                            @if ($canBulk)
                                <td class="sticky start-0 z-10 w-11 bg-surface px-3 py-3 group-hover:bg-surface-alt">
                                    <input type="checkbox" name="ids[]" value="{{ $row->getKey() }}" form="{{ $id }}-bulk" aria-label="{{ __('codeart::table.select_row') }}"
                                        class="size-5 cursor-pointer rounded-sm border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" x-on:change="sync()" data-row-select>
                                </td>
                            @endif
                            @foreach ($columns as $column)
                                <td class="px-4 py-3 align-middle text-text {{ $loop->first ? 'sticky '.$first.' z-10 bg-surface group-hover:bg-surface-alt' : '' }} {{ $column->type === 'number' ? 'text-end' : '' }}">
                                    <x-codeart::table.cell :column="$column" :row="$row" />
                                </td>
                            @endforeach
                            @if ($rowActions !== [])
                                <td class="whitespace-nowrap px-4 py-2 text-end" data-actions-cell>
                                    <div class="flex items-center justify-end gap-1">
                                        @foreach ($rowActions as $action)
                                            @if ($action->shownFor($user, $row))
                                                @if ($action->isGet())
                                                    <x-codeart::icon-button :href="($action->url)($row)" :label="$action->label" :icon="$action->icon" size="sm" data-action="{{ $action->key }}" />
                                                @else
                                                    <x-codeart::confirm :action="($action->url)($row)" :method="$action->method" :title="$action->label" :text="$action->confirm" :confirm="$action->label" :cancel="__('codeart::ui.c.cancel')" :variant="$action->danger() ? 'danger' : 'secondary'" size="sm" :icon="$action->icon" :icon-only="true" data-action="{{ $action->key }}" class="flex" />
                                                @endif
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3" data-footer>
            <p class="text-sm text-text-muted" data-showing>{{ __('codeart::table.showing', ['from' => number_format($result->from(), 0, ',', '.'), 'to' => number_format($result->to(), 0, ',', '.'), 'total' => number_format($result->total, 0, ',', '.')]) }}</p>
            <x-codeart::table.pagination :result="$result" />
            <form method="GET" action="{{ $result->baseUrl }}" class="flex items-center gap-2">
                @foreach ($result->hiddenFields(['page', 'per_page']) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <label for="{{ $id }}-per-page" class="text-sm text-text-muted">{{ __('codeart::table.per_page') }}</label>
                <div class="w-24"><x-codeart::table.select name="per_page" id="{{ $id }}-per-page" :label="__('codeart::table.per_page')" :options="$perPage" :value="$state->perPage" x-on:change="$el.form.requestSubmit()" /></div>
                <noscript><button type="submit" class="h-11 rounded-control border border-border bg-surface px-3 text-sm text-text">{{ __('codeart::table.apply') }}</button></noscript>
            </form>
        </div>
    @endif
</div>
