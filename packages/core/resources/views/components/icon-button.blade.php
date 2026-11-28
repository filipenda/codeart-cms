@props(['label', 'icon', 'variant' => 'neutral', 'size' => 'md', 'href' => null, 'type' => 'button', 'loading' => false, 'disabled' => false])
@php
    // Square ghost icon button (sm 32px, md 36px). Variants: neutral, danger (icon color + soft background on hover/focus only,
    // never filled at rest). Renders <a> with `href`, otherwise <button> (type="submit" inside a form for POST actions).
    $classes = 'inline-flex shrink-0 select-none items-center justify-center rounded-control p-0 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-disabled:pointer-events-none aria-disabled:opacity-60 disabled:pointer-events-none disabled:opacity-60 '
        .($size === 'sm' ? 'size-8 ' : 'size-9 ')
        .($variant === 'danger'
            ? 'bg-transparent text-danger hover:bg-danger-bg focus-visible:bg-danger-bg'
            : 'bg-transparent text-text-muted hover:bg-surface-sunken hover:text-text focus-visible:bg-surface-sunken focus-visible:text-text');
    $inactive = $loading || $disabled;
    $iconClass = $size === 'sm' ? 'size-4' : 'size-5';
@endphp
@if ($href !== null)
    <a href="{{ $inactive ? '#' : $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}
        @if ($inactive) aria-disabled="true" tabindex="-1" @endif @if ($loading) aria-busy="true" @endif>
        @if ($loading)<svg class="{{ $iconClass }} shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
        @else<x-codeart::icon :name="$icon" :class="$iconClass" />@endif
    </a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }} @disabled($disabled)
        @if ($loading) aria-disabled="true" aria-busy="true" @endif>
        @if ($loading)<svg class="{{ $iconClass }} shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
        @else<x-codeart::icon :name="$icon" :class="$iconClass" />@endif
    </button>
@endif
