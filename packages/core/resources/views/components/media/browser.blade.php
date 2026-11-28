@props(['mode' => 'page', 'locales' => [], 'accept' => ''])
@php
    $isPage = $mode === 'page';
    $config = \Codeart\Core\Media\BrowserConfig::make($mode);
    $t = fn (string $key) => __('codeart::media.'.$key);
@endphp
{{-- The media browser (library page and picker dialog share it). Alpine component `codeartMedia`, see resources/js/media.js. --}}
<div x-data="codeartMedia(@js($config))" x-on:codeart:media-open.window="if ($event.detail.token !== undefined && {{ $isPage ? 'false' : 'true' }}) open($event.detail)"
    
     data-media-browser="{{ $mode }}">

    {{-- Confirmation dialog: the server still decides (it issues the one-time token after this answer). --}}
    <dialog x-ref="confirmDialog" data-modal role="alertdialog" aria-labelledby="media-confirm-title" aria-describedby="media-confirm-text" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-card border border-border bg-surface p-0 text-text shadow-card backdrop:bg-scrim" data-media-confirm>
        <form method="dialog">
            <div class="border-b border-border px-6 py-4"><h2 id="media-confirm-title" class="text-lg font-semibold" x-text="ask.title"></h2></div>
            <p id="media-confirm-text" class="px-6 py-5 text-sm" x-text="ask.text"></p>
            <div class="flex flex-wrap justify-end gap-3 border-t border-border px-6 py-4">
                <x-codeart::button type="submit" variant="secondary" size="md" :full="false" value="no">{{ __('codeart::ui.c.cancel') }}</x-codeart::button>
                <x-codeart::button type="submit" variant="danger" size="md" :full="false" value="yes" data-media-confirm-yes><span x-text="ask.button"></span></x-codeart::button>
            </div>
        </form>
    </dialog>

    @if ($isPage)
        @include('codeart::media.body', ['config' => $config, 'isPage' => $isPage, 'locales' => $locales, 'accept' => $accept, 't' => $t])
        {{-- Detail drawer: preview, texts per language, copy URL, replace, used in, delete --}}
        <dialog x-ref="drawer" data-modal class="m-0 ms-auto h-dvh max-h-dvh w-full max-w-md overflow-y-auto border-s border-border bg-surface p-0 text-text shadow-card backdrop:bg-scrim" aria-labelledby="media-detail-title" x-on:close="detail = null" data-media-drawer>
            <template x-if="detail">
                <div class="flex min-h-full flex-col">
                    <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <h2 id="media-detail-title" class="min-w-0 truncate text-lg font-semibold" x-text="detail.media.name"></h2>
                        <button type="button" class="rounded-md p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:click="closeDetail()" aria-label="{{ __('codeart::ui.c.close') }}"><x-codeart::icon name="x" class="size-5" /></button>
                    </div>
                    <div class="flex-1 space-y-5 p-5 text-sm">
                        <div class="flex items-center justify-center overflow-hidden rounded-card bg-surface-alt p-2">
                            <img x-show="detail.media.thumb" x-bind:src="detail.media.thumb" x-bind:alt="detail.media.alt" class="max-h-64 max-w-full object-contain">
                            <x-codeart::icon x-show="!detail.media.thumb" name="file-text" class="size-16 text-text-muted" />
                        </div>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-text-muted">
                            <dt>{{ $t('col.type') }}</dt><dd class="text-text" x-text="detail.media.mime"></dd>
                            <dt>{{ $t('col.size') }}</dt><dd class="text-text" x-text="human(detail.media.size)"></dd>
                            <dt x-show="detail.media.width">{{ $t('col.dimensions') }}</dt><dd class="text-text" x-show="detail.media.width" x-text="detail.media.width + ' × ' + detail.media.height"></dd>
                            <dt>{{ $t('col.date') }}</dt><dd class="text-text" x-text="(detail.media.created_at ?? '').slice(0, 10)"></dd>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <x-codeart::button type="button" variant="secondary" size="sm" :full="false" icon-left="copy" x-on:click="copyUrl(detail.media.url)" data-copy-url>{{ $t('copy_url') }}</x-codeart::button>
                            <a x-bind:href="detail.media.url" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-2 rounded-control border border-border px-3.5 text-sm hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus"><x-codeart::icon name="external-link" class="size-4" /> {{ $t('open') }}</a>
                        </div>

                        @if ($config['can']['update'])
                            {{-- Alt text and title per language (one panel per language) --}}
                            <div class="space-y-3" data-media-texts>
                                <div role="tablist" class="flex flex-wrap gap-1" aria-label="{{ __('codeart::publishing.languages') }}">
                                    @foreach ($locales as $code => $name)
                                        <button type="button" role="tab" class="rounded-control border border-border px-3 py-1.5 text-sm font-semibold text-text-muted aria-selected:bg-primary aria-selected:text-on-primary focus-visible:outline-2 focus-visible:outline-focus" x-bind:aria-selected="detail.locale === @js($code)" x-on:click="detail.locale = @js($code)">{{ $name }}</button>
                                    @endforeach
                                </div>
                                @foreach ($locales as $code => $name)
                                    <div x-show="detail.locale === @js($code)" class="space-y-3" role="tabpanel">
                                        <label class="block"><span class="mb-1 block font-medium">{{ $t('alt') }}</span><input type="text" maxlength="255" x-model="detail.translations[@js($code)].alt" class="h-11 w-full rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus"></label>
                                        <label class="block"><span class="mb-1 block font-medium">{{ $t('title_label') }}</span><input type="text" maxlength="255" x-model="detail.translations[@js($code)].title" class="h-11 w-full rounded-control border border-border bg-surface px-3 text-text focus-visible:outline-2 focus-visible:outline-focus"></label>
                                    </div>
                                @endforeach
                                <x-codeart::button type="button" size="md" :full="false" icon-left="check" x-on:click="saveDetail()" x-bind:disabled="detailBusy" data-save-detail>{{ __('codeart::publishing.save') }}</x-codeart::button>
                            </div>
                            @if ($config['can']['upload'])
                                <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-control border border-border px-3.5 text-sm hover:bg-surface-alt focus-within:outline-2 focus-within:outline-focus">
                                    <x-codeart::icon name="refresh-cw" class="size-4" /> {{ $t('replace') }}<input type="file" class="sr-only" x-on:change="replaceFile($event)" data-replace-input></label>
                            @endif
                        @endif

                        <div data-media-usage>
                            <h3 class="mb-1 font-semibold">{{ $t('used_in') }}</h3>
                            <p x-show="detail.places.length === 0" class="text-text-muted">{{ $t('unused') }}</p>
                            <ul class="list-disc ps-5 text-text-muted"><template x-for="place in detail.places"><li><a x-bind:href="place.url" x-show="place.url" class="underline" x-text="place.label"></a><span x-show="!place.url" x-text="place.label"></span> <span x-text="'(' + place.type + ' · ' + place.field + ')'"></span></li></template></ul>
                        </div>
                    </div>
                    @if ($config['can']['delete'])
                        <div class="border-t border-border px-5 py-4">
                            <x-codeart::button type="button" variant="danger" size="md" :full="false" icon-left="trash-2" x-on:click="deleteDetail()" data-delete-detail>{{ $t('delete.button') }}</x-codeart::button>
                        </div>
                    @endif
                </div>
            </template>
        </dialog>
    @else
        <dialog x-ref="dialog" data-modal class="m-auto w-[calc(100%-2rem)] max-w-6xl rounded-card border border-border bg-surface p-0 text-text shadow-card backdrop:bg-scrim" aria-labelledby="media-picker-title" data-media-picker>
            <div class="flex items-center justify-between gap-3 border-b border-border px-6 py-4">
                <h2 id="media-picker-title" class="text-lg font-semibold">{{ $t('picker.title') }}</h2>
                <button type="button" class="rounded-md p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:click="$refs.dialog.close()" aria-label="{{ __('codeart::ui.c.close') }}"><x-codeart::icon name="x" class="size-5" /></button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-6" data-picker-body>@include('codeart::media.body', ['config' => $config, 'isPage' => $isPage, 'locales' => $locales, 'accept' => $accept, 't' => $t])</div>
            <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
                <x-codeart::button type="button" variant="secondary" size="md" :full="false" x-on:click="$refs.dialog.close()">{{ __('codeart::ui.c.cancel') }}</x-codeart::button>
                <x-codeart::button type="button" size="md" :full="false" x-bind:disabled="selected.length === 0" x-on:click="confirmPick()" data-pick-confirm>{{ $t('picker.choose') }}</x-codeart::button>
            </div>
        </dialog>
    @endif
</div>
