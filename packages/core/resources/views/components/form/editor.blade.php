@props(['name', 'label', 'id' => null, 'hint' => null, 'bag' => null, 'value' => null, 'rows' => 10])
@php
    // Rich-text field (TipTap, docs/editor.md). Submits plain HTML in the textarea named `$name`; without JS it stays a
    // textarea. The server sanitizes on save. Needs the `<x-codeart::media.picker />` once on the page (included below).
    $id ??= 'field-'.str_replace(['[', ']'], '-', $name);
    $error = ($bag ?? $errors)->first($name);
    $value ??= old($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $source = \Illuminate\Support\Facades\Gate::allows('manage-modules');
    $css = \Codeart\Core\Editor\EditorStyles::urls();
    $t = fn (string $key) => __('codeart::editor.'.$key);
    $config = ['label' => $label, 'css' => $css, 'labels' => ['link_invalid' => $t('link_invalid')]];
    $btn = 'inline-flex size-9 items-center justify-center rounded-control text-text-muted hover:bg-surface-sunken hover:text-text focus-visible:outline-2 focus-visible:outline-focus disabled:opacity-40 aria-pressed:bg-primary-soft aria-pressed:text-text';
    $groups = [
        [['bold', 'bold'], ['italic', 'italic'], ['underline', 'underline'], ['strike', 'strikethrough'], ['code', 'code']],
        [['bulletList', 'list'], ['orderedList', 'list-ordered'], ['blockquote', 'quote'], ['rule', 'minus']],
    ];
@endphp
<div {{ $attributes->class('space-y-2') }} data-field data-editor x-data="codeartEditor(@js($config))">
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-text">{{ $label }}@if ($attributes->has('required'))<span class="ms-0.5 text-danger-text" aria-hidden="true">*</span>@endif</label>

    <div class="overflow-hidden rounded-control border border-border bg-surface focus-within:border-text" x-bind:class="{ 'border-danger': {{ $error ? 'true' : 'false' }} }">
        {{-- Toolbar: only shown once the editor started (x-show), so without JS the textarea below is the whole field. --}}
        <div x-show="ready" x-cloak hidden x-bind:hidden="!ready" role="toolbar" aria-label="{{ $t('toolbar') }}" aria-controls="{{ $id }}" class="flex flex-wrap items-center gap-1 border-b border-border bg-surface-alt p-1.5">
            <button type="button" class="{{ $btn }}" title="{{ $t('undo') }} (Ctrl+Z)" aria-label="{{ $t('undo') }}" x-on:click="run('undo')" x-bind:disabled="!active.canUndo || source"><x-codeart::icon name="undo-2" class="size-4" /></button>
            <button type="button" class="{{ $btn }}" title="{{ $t('redo') }} (Ctrl+Shift+Z)" aria-label="{{ $t('redo') }}" x-on:click="run('redo')" x-bind:disabled="!active.canRedo || source"><x-codeart::icon name="redo-2" class="size-4" /></button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
            <select aria-label="{{ $t('block') }}" class="h-9 rounded-control border border-border bg-surface px-2 text-sm text-text focus-visible:outline-2 focus-visible:outline-focus" x-bind:value="active.block" x-on:change="block($event.target.value)" x-bind:disabled="source">
                <option value="p">{{ $t('paragraph') }}</option>
                <option value="h2">{{ $t('h2') }}</option>
                <option value="h3">{{ $t('h3') }}</option>
                <option value="h4">{{ $t('h4') }}</option>
            </select>
            @foreach ($groups as $group)
                <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
                @foreach ($group as [$command, $icon])
                    <button type="button" class="{{ $btn }}" title="{{ $t($command) }}" aria-label="{{ $t($command) }}" aria-pressed="false" x-bind:aria-pressed="!!active.{{ $command }}" x-bind:disabled="source" x-on:click="run('{{ $command }}')"><x-codeart::icon :name="$icon" class="size-4" /></button>
                @endforeach
            @endforeach
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
            <button type="button" class="{{ $btn }}" title="{{ $t('link') }} (Ctrl+K)" aria-label="{{ $t('link') }}" aria-pressed="false" x-bind:aria-pressed="!!active.link" x-bind:disabled="source" x-on:click="openLink()"><x-codeart::icon name="link" class="size-4" /></button>
            <button type="button" class="{{ $btn }}" title="{{ $t('image') }}" aria-label="{{ $t('image') }}" x-bind:disabled="source" x-on:click="pickImage()"><x-codeart::icon name="image" class="size-4" /></button>
            <button type="button" class="{{ $btn }}" title="{{ $t('table') }}" aria-label="{{ $t('table') }}" x-bind:disabled="source" x-on:click="run('table')"><x-codeart::icon name="table" class="size-4" /></button>
            <button type="button" class="{{ $btn }}" title="{{ $t('clear') }}" aria-label="{{ $t('clear') }}" x-bind:disabled="source" x-on:click="run('clear')"><x-codeart::icon name="remove-formatting" class="size-4" /></button>
            @if ($source)
                <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
                <button type="button" class="{{ $btn }}" title="{{ $t('source') }}" aria-label="{{ $t('source') }}" aria-pressed="false" x-bind:aria-pressed="source" data-editor-source x-on:click="toggleSource()"><x-codeart::icon name="file-code" class="size-4" /></button>
            @endif
        </div>
        <div x-show="ready && inTable && !source" x-cloak hidden x-bind:hidden="!(ready && inTable && !source)" role="toolbar" aria-label="{{ $t('table_tools') }}" class="flex flex-wrap items-center gap-1 border-b border-border bg-surface px-2 py-1.5 text-sm">
            @foreach (['rowAfter' => 'row_add', 'colAfter' => 'col_add', 'rowDelete' => 'row_delete', 'colDelete' => 'col_delete', 'tableDelete' => 'table_delete'] as $command => $key)
                <button type="button" class="rounded-control px-2.5 py-1 text-text-muted hover:bg-surface-sunken hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:click="run('{{ $command }}')">{{ $t($key) }}</button>
            @endforeach
        </div>

        {{-- The editable area lives in this host's shadow root (isolation from admin and site CSS). --}}
        <div x-ref="host" x-show="ready && !source" x-cloak hidden x-bind:hidden="!ready || source" data-editor-host></div>
        @if ($source)
            <textarea x-ref="source" x-show="source" x-cloak hidden x-bind:hidden="!source" rows="{{ $rows }}" spellcheck="false" dir="ltr" aria-label="{{ $t('source') }}"
                class="block min-h-56 w-full border-0 bg-surface px-4 py-3 font-mono text-sm text-text focus-visible:outline-2 focus-visible:outline-focus"></textarea>
        @endif

        {{-- What is submitted; also the whole field without JS. --}}
        <textarea x-ref="input" id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" data-editor-input
            @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($attributes->has('required')) required @endif
            class="block w-full border-0 bg-surface px-4 py-3 text-base text-text focus-visible:outline-2 focus-visible:outline-focus">{{ $value }}</textarea>
    </div>

    <dialog x-ref="linkDialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-card border border-border bg-surface p-0 text-text shadow-card backdrop:bg-scrim" aria-labelledby="{{ $id }}-link-title">
        <form method="dialog" x-on:submit.prevent="applyLink()" class="space-y-4 p-6">
            <h2 id="{{ $id }}-link-title" class="text-lg font-semibold">{{ $t('link') }}</h2>
            <div class="space-y-2">
                <label for="{{ $id }}-link-url" class="block text-sm font-medium text-text">{{ $t('link_url') }}</label>
                <input x-ref="linkUrl" id="{{ $id }}-link-url" type="text" inputmode="url" autocomplete="off" x-model="link.url" placeholder="https://"
                    class="block w-full rounded-control border border-border bg-surface px-4 py-2.5 text-base text-text focus-visible:outline-2 focus-visible:outline-focus">
                <p class="text-sm font-medium text-danger-text" x-show="link.error" x-text="link.error" x-cloak></p>
                <p class="text-sm text-text-muted">{{ $t('link_hint') }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="link.blank"> {{ $t('link_blank') }}</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="link.nofollow"> {{ $t('link_nofollow') }}</label>
            <div class="flex flex-wrap justify-end gap-3">
                <x-codeart::button type="button" variant="ghost" size="md" :full="false" x-show="active.link" x-cloak x-on:click="removeLink()">{{ $t('link_remove') }}</x-codeart::button>
                <x-codeart::button type="button" variant="secondary" size="md" :full="false" x-on:click="$refs.linkDialog.close()">{{ $t('cancel') }}</x-codeart::button>
                <x-codeart::button type="submit" size="md" :full="false">{{ $t('apply') }}</x-codeart::button>
            </div>
        </form>
    </dialog>

    @if ($hint) <p id="{{ $id }}-hint" class="text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($error)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm font-medium text-danger-text"><x-codeart::icon name="alert" class="mt-0.5 size-4" /><span>{{ $error }}</span></p>
    @endif
</div>
@once
    {{-- The media picker, once per page (resolved at run time). --}}
    @if (view()->exists('codeart::components.media.picker'))
        <x-dynamic-component component="codeart::media.picker" />
    @endif
@endonce
