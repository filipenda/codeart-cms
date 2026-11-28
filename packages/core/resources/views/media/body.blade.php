{{-- Body of the media browser (folders + toolbar + grid/list). Included by components/media/browser.blade.php inside the Alpine scope. --}}
<div class="relative grid gap-4 lg:grid-cols-[14rem_minmax(0,1fr)]" x-on:dragover.prevent="dragging = {{ $config['can']['upload'] ? 'true' : 'false' }}" x-on:dragleave.self="dragging = false" x-on:drop.prevent="drop($event)">
    {{-- Folder tree --}}
    <aside class="space-y-2" aria-label="{{ $t('folders') }}">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-text">{{ $t('folders') }}</h2>
            @if ($config['can']['update'])
                <button type="button" class="rounded-control p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:click="createFolder()" aria-label="{{ $t('folder.new') }}" title="{{ $t('folder.new') }}"><x-codeart::icon name="plus" class="size-4" /></button>
            @endif
        </div>
        <ul class="space-y-0.5 text-sm" data-folder-tree>
            <li><button type="button" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-start hover:bg-surface-alt aria-[current=true]:bg-primary-soft aria-[current=true]:font-semibold" x-bind:aria-current="filters.folder === '' ? 'true' : 'false'" x-on:click="openFolder(null)"><x-codeart::icon name="images" class="size-4" /> {{ $t('all_files') }}</button></li>
            <li><button type="button" class="flex w-full items-center gap-2 rounded-control px-2 py-1.5 text-start hover:bg-surface-alt aria-[current=true]:bg-primary-soft aria-[current=true]:font-semibold" x-bind:aria-current="filters.folder === 'root' ? 'true' : 'false'" x-on:click="filters.folder = 'root'; load(true)"><x-codeart::icon name="folder" class="size-4" /> {{ $t('no_folder') }}</button></li>
            <template x-for="folder in folders" :key="folder.id">
                <li class="group flex items-center" x-bind:style="'padding-inline-start:' + (folder.depth * 0.75) + 'rem'">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-control px-2 py-1.5 text-start hover:bg-surface-alt aria-[current=true]:bg-primary-soft aria-[current=true]:font-semibold" x-bind:aria-current="filters.folder === String(folder.id) ? 'true' : 'false'" x-on:click="openFolder(folder.id)"><x-codeart::icon name="folder-open" class="size-4 shrink-0" /><span class="truncate" x-text="folder.name"></span></button>
                    @if ($isPage && $config['can']['update'])
                        <button type="button" class="rounded-control p-1 text-text-muted opacity-0 hover:text-text focus:opacity-100 group-hover:opacity-100 focus-visible:outline-2 focus-visible:outline-focus" x-on:click="renameFolder(folder)" aria-label="{{ $t('folder.rename') }}"><x-codeart::icon name="pencil" class="size-3.5" /></button>
                    @endif
                    @if ($isPage && $config['can']['delete'])
                        <button type="button" class="rounded-control p-1 text-text-muted opacity-0 hover:text-danger focus:opacity-100 group-hover:opacity-100 focus-visible:outline-2 focus-visible:outline-focus" x-on:click="deleteFolder(folder)" aria-label="{{ $t('folder.delete') }}"><x-codeart::icon name="trash-2" class="size-3.5" /></button>
                    @endif
                </li>
            </template>
        </ul>
    </aside>

    <div class="min-w-0 space-y-4">
        {{-- Toolbar: search, filters, sort, view --}}
        <div class="flex flex-wrap items-end gap-2" role="search">
            <label class="min-w-48 flex-1 text-sm"><span class="sr-only">{{ $t('search') }}</span>
                <input type="search" x-model="filters.q" x-on:input="refilter()" placeholder="{{ $t('search') }}" class="h-11 w-full rounded-control border border-border bg-surface px-3 text-text placeholder:text-text-muted focus-visible:outline-2 focus-visible:outline-focus" data-media-search></label>
            <label class="text-sm"><span class="sr-only">{{ $t('filter.type') }}</span>
                <select x-model="filters.type" x-on:change="load(true)" class="h-11 rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus">
                    <option value="">{{ $t('filter.all_types') }}</option>
                    @foreach (['image', 'pdf', 'document', 'archive'] as $kind) <option value="{{ $kind }}">{{ $t('filter.'.$kind) }}</option> @endforeach
                </select></label>
            @if ($isPage)
                <label class="text-sm"><span class="sr-only">{{ $t('filter.from') }}</span><input type="date" x-model="filters.from" x-on:change="load(true)" class="h-11 rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus" title="{{ $t('filter.from') }}"></label>
                <label class="text-sm"><span class="sr-only">{{ $t('filter.to') }}</span><input type="date" x-model="filters.to" x-on:change="load(true)" class="h-11 rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus" title="{{ $t('filter.to') }}"></label>
                <label class="text-sm"><span class="sr-only">{{ $t('filter.uploader') }}</span>
                    <select x-model="filters.uploader" x-on:change="load(true)" class="h-11 rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus">
                        <option value="">{{ $t('filter.anyone') }}</option><option value="me">{{ $t('filter.mine') }}</option>
                    </select></label>
            @endif
            <label class="text-sm"><span class="sr-only">{{ $t('sort.label') }}</span>
                <select x-model="filters.sort" x-on:change="load(true)" class="h-11 rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus">
                    @foreach (['newest', 'oldest', 'name', 'size'] as $sort) <option value="{{ $sort }}">{{ $t('sort.'.$sort) }}</option> @endforeach
                </select></label>
            <div class="inline-flex overflow-hidden rounded-control border border-border" role="group" aria-label="{{ $t('view') }}">
                <button type="button" class="h-11 px-3 text-text-muted hover:bg-surface-alt aria-pressed:bg-primary aria-pressed:text-on-primary focus-visible:outline-2 focus-visible:outline-focus" x-bind:aria-pressed="view === 'grid'" x-on:click="setView('grid')" aria-label="{{ $t('view_grid') }}"><x-codeart::icon name="layout-grid" class="size-5" /></button>
                <button type="button" class="h-11 px-3 text-text-muted hover:bg-surface-alt aria-pressed:bg-primary aria-pressed:text-on-primary focus-visible:outline-2 focus-visible:outline-focus" x-bind:aria-pressed="view === 'list'" x-on:click="setView('list')" aria-label="{{ $t('view_list') }}"><x-codeart::icon name="list" class="size-5" /></button>
            </div>
            @if ($config['can']['upload'])
                <label class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-control bg-primary px-5 text-sm font-medium text-on-primary hover:bg-primary-hover focus-within:outline-2 focus-within:outline-focus">
                    <x-codeart::icon name="upload" class="size-5" /> {{ $t('upload') }}
                    <input type="file" multiple class="sr-only" accept="{{ $accept }}" x-on:change="pickFiles($event)" data-media-input>
                </label>
            @endif
        </div>

        {{-- Effective upload limit, in plain words --}}
        @if ($config['can']['upload'])
            <p class="text-sm text-text-muted" data-upload-limit>{{ __('codeart::media.limit.short', ['limit' => $config['limitHuman']]) }}</p>
        @endif

        {{-- Bulk bar --}}
        <div x-show="selected.length > 0" x-cloak class="flex flex-wrap items-center gap-2 rounded-card border border-border bg-surface px-4 py-2 text-sm" data-bulk-bar>
            <span class="font-semibold" x-text="selected.length + ' {{ $t('selected') }}'"></span>
            @if ($isPage && $config['can']['update'])
                <label><span class="sr-only">{{ $t('move_to') }}</span>
                    <select class="h-9 rounded-control border border-border bg-surface px-2 text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:change="moveSelected($event)" data-bulk-move>
                        <option value="">{{ $t('move_to') }}…</option><option value="root">{{ $t('no_folder') }}</option>
                        <template x-for="folder in folders" :key="folder.id"><option x-bind:value="folder.id" x-text="'— '.repeat(folder.depth) + folder.name"></option></template>
                    </select></label>
            @endif
            @if ($isPage && $config['can']['delete'])
                <x-codeart::button type="button" variant="danger" size="sm" :full="false" icon-left="trash-2" x-on:click="deleteSelected(false)" data-bulk-delete>{{ $t('delete.button') }}</x-codeart::button>
            @endif
            <button type="button" class="text-text-muted underline focus-visible:outline-2 focus-visible:outline-focus" x-on:click="selected = []">{{ $t('clear_selection') }}</button>
        </div>

        {{-- Blocked deletes: where the files are used --}}
        <div x-show="blocked" x-cloak class="space-y-2 rounded-card border border-warning bg-surface p-4 text-sm" role="alert" data-blocked>
            <p class="font-semibold">{{ $t('delete.in_use') }}</p>
            <template x-for="item in blocked ?? []" :key="item.id">
                <div><p class="font-medium" x-text="item.name"></p>
                    <ul class="ms-4 list-disc text-text-muted"><template x-for="place in item.places"><li><a x-bind:href="place.url" x-show="place.url" class="underline" x-text="place.label"></a><span x-show="!place.url" x-text="place.label"></span> <span x-text="'(' + place.type + ' · ' + place.field + ')'"></span></li></template></ul></div>
            </template>
            <div class="flex gap-2">
                <x-codeart::button type="button" variant="danger" size="sm" :full="false" x-on:click="forceBlocked()">{{ $t('delete.force_button') }}</x-codeart::button>
                <x-codeart::button type="button" variant="secondary" size="sm" :full="false" x-on:click="blocked = null">{{ $t('delete.keep') }}</x-codeart::button>
            </div>
        </div>

        {{-- Upload progress --}}
        <ul x-show="uploads.length" x-cloak class="space-y-2 text-sm" data-uploads aria-live="polite">
            <template x-for="u in uploads" :key="u.id">
                <li class="rounded-control border border-border bg-surface px-3 py-2">
                    <div class="flex items-center justify-between gap-3"><span class="truncate" x-text="u.name"></span>
                        <span class="shrink-0 text-text-muted" x-text="u.status === 'sending' ? u.progress + '%' : (u.status === 'done' ? '{{ $t('done') }}' : '')"></span></div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-alt" x-show="u.status === 'sending' || u.status === 'done'"><div class="h-full bg-primary" x-bind:style="'width:' + u.progress + '%'"></div></div>
                    <p class="mt-1 text-danger" x-show="u.status === 'error'" x-text="u.error"></p>
                    <div x-show="u.status === 'duplicate'" class="mt-1 space-y-1"><p x-text="u.error"></p>
                        <div class="flex gap-2"><button type="button" class="font-semibold underline" x-on:click="reuse(u)">{{ $t('duplicate_reuse') }}</button><button type="button" class="underline" x-on:click="keepBoth(u)">{{ $t('duplicate_keep') }}</button></div></div>
                </li>
            </template>
            <li><button type="button" class="text-text-muted underline" x-on:click="clearUploads()">{{ $t('clear_list') }}</button></li>
        </ul>

        <p x-show="message" x-text="message" class="text-sm text-danger" role="alert"></p>

        {{-- Drop zone hint --}}
        <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-card border-2 border-dashed border-primary bg-surface/80 text-lg font-semibold">{{ $t('drop_here') }}</div>

        {{-- Grid --}}
        <ul x-show="view === 'grid'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6" data-media-grid>
            <template x-for="item in items" :key="item.id">
                <li class="group relative overflow-hidden rounded-card border border-border bg-surface aria-selected:outline-2 aria-selected:outline-primary" x-bind:aria-selected="isSelected(item.id)">
                    <button type="button" class="block w-full text-start focus-visible:outline-2 focus-visible:outline-focus" x-on:click="select(item, $event)" x-bind:aria-label="item.name">
                        <span class="flex aspect-square items-center justify-center bg-surface-alt">
                            <img x-show="item.thumb" x-bind:src="item.thumb" x-bind:alt="item.alt" loading="lazy" class="size-full object-cover">
                            <x-codeart::icon x-show="!item.thumb" name="file-text" class="size-10 text-text-muted" />
                        </span>
                        <span class="block truncate px-2 py-1.5 text-xs text-text" x-text="item.name"></span>
                    </button>
                    <label class="absolute start-2 top-2 inline-flex size-7 cursor-pointer items-center justify-center rounded-control border border-border bg-surface"><span class="sr-only">{{ $t('select') }}</span>
                        <input type="checkbox" class="size-4" x-bind:checked="isSelected(item.id)" x-on:change="toggle(item, $event)"></label>
                </li>
            </template>
        </ul>

        {{-- List --}}
        <div x-show="view === 'list'" x-cloak class="overflow-x-auto rounded-card border border-border bg-surface">
            <table class="w-full text-sm" data-media-list>
                <thead class="border-b border-border text-start text-text-muted"><tr><th class="w-10 p-2"><span class="sr-only">{{ $t('select') }}</span></th><th class="p-2 text-start">{{ $t('col.file') }}</th><th class="p-2 text-start">{{ $t('col.type') }}</th><th class="p-2 text-end">{{ $t('col.size') }}</th><th class="p-2 text-start">{{ $t('col.date') }}</th></tr></thead>
                <tbody>
                    <template x-for="item in items" :key="item.id">
                        <tr class="border-b border-border last:border-0 aria-selected:bg-primary-soft" x-bind:aria-selected="isSelected(item.id)">
                            <td class="p-2"><input type="checkbox" class="size-4" x-bind:checked="isSelected(item.id)" x-on:change="toggle(item, $event)" x-bind:aria-label="item.name"></td>
                            <td class="p-2"><button type="button" class="flex items-center gap-3 text-start focus-visible:outline-2 focus-visible:outline-focus" x-on:click="select(item, $event)">
                                <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-control bg-surface-alt"><img x-show="item.thumb" x-bind:src="item.thumb" alt="" loading="lazy" class="size-full object-cover"><x-codeart::icon x-show="!item.thumb" name="file-text" class="size-5 text-text-muted" /></span>
                                <span class="truncate font-medium" x-text="item.name"></span></button></td>
                            <td class="p-2 text-text-muted" x-text="item.mime"></td>
                            <td class="p-2 text-end text-text-muted" x-text="human(item.size)"></td>
                            <td class="p-2 text-text-muted" x-text="(item.created_at ?? '').slice(0, 10)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p x-show="loaded && items.length === 0 && !loading" x-cloak class="rounded-card border border-dashed border-border p-8 text-center text-text-muted" data-media-empty>{{ $t('empty') }}</p>
        <div class="flex justify-center"><x-codeart::button type="button" variant="secondary" size="md" :full="false" x-show="next" x-on:click="load(false)" x-bind:disabled="loading" data-media-more>{{ $t('load_more') }}</x-codeart::button></div>
    </div>

</div>
