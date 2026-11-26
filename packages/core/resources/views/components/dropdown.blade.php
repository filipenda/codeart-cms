@props(['label' => null, 'id' => null, 'align' => 'end', 'width' => 'w-56', 'triggerClass' => null])
@php
    $id ??= 'dropdown-'.substr(md5((string) $label.$width.$align), 0, 8);
    $triggerClass ??= 'inline-flex h-11 items-center gap-2 rounded-control border border-border bg-surface px-4 text-sm font-medium text-text hover:bg-surface-alt';
@endphp
{{-- The one dropdown (also the user menu): aria-expanded button + panel. Slot `trigger` = button content, default slot = panel.
     Items marked data-menu-item take part in the arrow-key navigation. Esc closes and refocuses the button, tabbing out closes. --}}
<div {{ $attributes->class('relative') }} x-data="codeartDropdown" x-on:keydown="onKey($event)" x-on:focusout="onFocusOut($event)" x-on:click.outside="close(false)">
    <button type="button" x-ref="button" x-on:click="toggle()" aria-haspopup="true" aria-controls="{{ $id }}" aria-expanded="false" x-bind:aria-expanded="open ? 'true' : 'false'"
        @if ($label) aria-label="{{ $label }}" @endif
        class="cursor-pointer focus-visible:outline-2 focus-visible:outline-focus {{ $triggerClass }}">{{ $trigger }}</button>
    <div id="{{ $id }}" x-show="open" x-cloak x-ref="panel"
        class="absolute {{ $align === 'start' ? 'start-0' : 'end-0' }} z-20 mt-2 {{ $width }} max-w-[calc(100vw-2rem)] rounded-control border border-border bg-surface p-1 shadow-card">
        {{ $slot }}
    </div>
</div>
