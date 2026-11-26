@props(['variant' => 'primary', 'type' => 'submit', 'size' => 'lg', 'icon' => null, 'iconLeft' => null, 'href' => null, 'full' => true, 'loading' => false, 'disabled' => false])
@php
    // Variants: primary, secondary, ghost, danger. Sizes: sm (36px), md (44px), lg (52px, the default of the sign-in screens).
    $classes = 'group inline-flex select-none items-center justify-center gap-2 rounded-control font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-disabled:pointer-events-none aria-disabled:opacity-70 disabled:pointer-events-none disabled:opacity-60 '
        .($full ? 'w-full ' : '')
        .match ($size) {
            'sm' => 'h-9 px-3.5 text-sm ',
            'md' => 'h-11 px-5 text-sm ',
            default => 'h-13 px-6 text-base ',
        }
        .match ($variant) {
            'secondary' => 'border border-border bg-surface text-text hover:bg-surface-alt',
            'ghost' => 'bg-transparent text-text hover:bg-surface-alt',
            'danger' => 'bg-danger text-on-danger hover:brightness-95',
            default => 'bg-primary text-on-primary hover:bg-primary-hover',
        };
    $inactive = $loading || $disabled;
    $iconSize = $size === 'sm' ? 'size-4' : 'size-5';
@endphp
@if ($href !== null)
    <a href="{{ $inactive ? '#' : $href }}" {{ $attributes->merge(['class' => $classes]) }}
        @if ($inactive) aria-disabled="true" tabindex="-1" @endif @if ($loading) aria-busy="true" @endif>
        @if ($loading) <svg class="{{ $iconSize }} shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
        @elseif ($iconLeft) <x-codeart::icon :name="$iconLeft" :class="$iconSize" /> @endif
        <span>{{ $slot }}</span>
        @if ($icon) <x-codeart::icon :name="$icon" :class="$iconSize" /> @endif
    </a>
@else
    {{-- Loading state: after the first submit the button ignores further clicks (no double submit). Without JS it is a plain submit button.
         `loading` starts it in that state (spinner, aria-busy); `disabled` is the real attribute. --}}
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @disabled($disabled)
        x-data="{ loading: {{ $loading ? 'true' : 'false' }} }"
        x-init="if ($el.form) { $el.form.addEventListener('submit', () => { loading = true }) }"
        x-on:click="if (loading) $event.preventDefault()"
        x-on:pageshow.window="loading = false"
        @if ($loading) aria-disabled="true" aria-busy="true" @endif
        x-bind:aria-disabled="loading ? 'true' : null"
        x-bind:aria-busy="loading ? 'true' : null">
        @if ($iconLeft)
            <x-codeart::icon :name="$iconLeft" :class="$iconSize" x-bind:hidden="loading" />
        @endif
        <span>{{ $slot }}</span>
        @if ($icon)
            <x-codeart::icon :name="$icon" :class="$iconSize" x-bind:hidden="loading" />
        @endif
        <svg class="{{ $iconSize }} shrink-0 animate-spin" @unless ($loading) hidden @endunless x-bind:hidden="!loading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
    </button>
@endif
