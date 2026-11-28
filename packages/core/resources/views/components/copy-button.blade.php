@props(['value', 'label' => null, 'copied' => null, 'variant' => 'secondary'])
{{-- Copies `value` to the clipboard (Clipboard API, textarea fallback) and says "Copied" for a moment. Needs JS: without it the
     button is not shown and the value stays visible next to it to select by hand. The value is only in the Alpine data. --}}
<span x-data="codeartCopy(@js((string) $value))" class="inline-flex" data-copy-button hidden x-bind:hidden="false">
    <button type="button" x-on:click="copy()" {{ $attributes->class(['inline-flex items-center gap-2 rounded-control border border-border bg-surface px-3 py-2 text-sm font-medium text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus']) }}>
        <span x-show="!copied" class="inline-flex items-center gap-2"><x-codeart::icon name="copy" class="size-4" /><span>{{ $label ?? __('codeart::ui.copy') }}</span></span>
        <span x-show="copied" x-cloak class="inline-flex items-center gap-2 text-success-text"><x-codeart::icon name="check" class="size-4" /><span>{{ $copied ?? __('codeart::ui.copied') }}</span></span>
    </button>
    <span class="sr-only" aria-live="polite" x-text="copied ? @js((string) ($copied ?? __('codeart::ui.copied'))) : (failed ? @js(__('codeart::ui.copy_failed')) : '')"></span>
</span>
