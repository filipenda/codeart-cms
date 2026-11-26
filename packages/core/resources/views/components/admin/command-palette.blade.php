@props(['items' => []])
{{-- Command palette (Ctrl+K). The items are rendered by the server, already filtered by what the user may open; the filtering runs in the browser. --}}
<script type="application/json" id="codeart-palette-items">{!! json_encode(array_values($items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<div x-show="paletteOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]" x-data="codeartPalette" x-effect="paletteOpen ? onOpen() : null">
    <div class="absolute inset-0 bg-scrim" x-on:click="closePalette()" aria-hidden="true"></div>
    <div role="dialog" aria-modal="true" aria-label="{{ __('codeart::admin.search.title') }}" x-ref="palette" class="relative w-full max-w-xl overflow-hidden rounded-card border border-border bg-surface shadow-card">
        <div class="flex items-center gap-3 border-b border-border px-4">
            <x-codeart::icon name="search" class="size-5 text-text-muted" />
            <input type="text" x-ref="paletteInput" x-model="query" x-on:keydown="onPaletteKey($event)" autocomplete="off" spellcheck="false"
                role="combobox" aria-expanded="true" aria-controls="codeart-palette-list" x-bind:aria-activedescendant="results.length ? 'palette-option-' + index : null"
                aria-label="{{ __('codeart::admin.search.label') }}" placeholder="{{ __('codeart::admin.search.placeholder') }}"
                class="h-14 w-full bg-transparent text-base text-text placeholder:text-text-muted focus:outline-none">
        </div>
        <ul id="codeart-palette-list" role="listbox" aria-label="{{ __('codeart::admin.search.title') }}" class="max-h-80 overflow-y-auto p-2">
            <template x-for="(item, i) in results" x-bind:key="item.url">
                <li role="presentation">
                    <a x-bind:id="'palette-option-' + i" role="option" tabindex="-1" x-bind:href="item.url" x-bind:aria-selected="i === index ? 'true' : 'false'"
                        x-on:mousemove="index = i" x-bind:class="i === index ? 'bg-primary text-on-primary' : 'text-text'"
                        class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm">
                        <span class="truncate font-medium" x-text="item.label"></span>
                        <span class="ms-auto truncate text-xs opacity-70" x-show="item.group" x-text="item.group"></span>
                    </a>
                </li>
            </template>
        </ul>
        <p x-show="results.length === 0" class="px-4 pb-6 pt-2 text-center text-sm text-text-muted">{{ __('codeart::admin.search.empty') }}</p>
        <p class="border-t border-border px-4 py-2 text-xs text-text-muted">{{ __('codeart::admin.search.hint') }}</p>
        <p class="sr-only" role="status" aria-live="polite" x-text="@js(__('codeart::admin.search.results', ['count' => ':count'])).replace(':count', results.length)"></p>
    </div>
</div>
